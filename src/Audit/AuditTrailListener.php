<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Category;
use App\Entity\Contract\PublishableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Media;
use App\Entity\MenuItem;
use App\Entity\ServiceRequest;
use App\Entity\Setting;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journal d'audit : toute création, modification, publication et suppression d'un contenu ou d'un compte est
 * enregistrée (qui, quoi, quand, valeurs avant/après), dans la même requête que la modification.
 *
 *  - Les traductions sont rattachées à leur contenu (les champs sont préfixés par la langue : « fr.title »).
 *  - Les secrets (mot de passe haché, secret TOTP) ne sont jamais enregistrés.
 *  - Les lignes sont écrites en SQL direct après le flush : le journal ne déclenche pas de nouveau flush.
 *  - Les demandes des usagers (ServiceRequest) ne sont journalisées que lorsqu'un agent les modifie.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditTrailListener
{
    /** Champs techniques dont la seule modification n'est pas un évènement d'audit. */
    private const array NOISE = ['updatedAt', 'updatedBy', 'lastLoginAt'];

    private const array SECRETS = ['password', 'totpSecret'];

    private const int MAX_VALUE_LENGTH = 500;

    /** @var array<int, array{subject: object, action: string, changes: array<string, array{0: mixed, 1: mixed}>}> */
    private array $pending = [];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly Security $security,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity, 'insert', $uow->getEntityChangeSet($entity), $uow);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity, 'update', $uow->getEntityChangeSet($entity), $uow);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collect($entity, 'delete', [], $uow);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];
        $connection = $args->getObjectManager()->getConnection();
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $actor = $this->security->getUser()?->getUserIdentifier() ?? 'system';

        foreach ($pending as $entry) {
            $subject = $entry['subject'];
            if ('update' === $entry['action'] && [] === $entry['changes']) {
                continue;
            }
            $connection->insert('audit_log', [
                'occurred_at' => $now,
                'actor' => mb_substr($actor, 0, 180),
                'action' => $entry['action'],
                'entity_class' => (new \ReflectionClass($subject))->getShortName(),
                'entity_id' => $this->id($subject),
                'label' => $this->label($subject),
                'changes' => json_encode($entry['changes'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function collect(object $entity, string $operation, array $changeSet, \Doctrine\ORM\UnitOfWork $uow): void
    {
        $subject = $entity instanceof TranslationInterface ? $entity->getTranslatable() : $entity;
        if (!$this->isAudited($subject, $operation)) {
            return;
        }

        $key = spl_object_id($subject);
        $prefix = $entity instanceof TranslationInterface ? $entity->getLocale().'.' : '';
        $changes = [];
        foreach ($changeSet as $field => [$old, $new]) {
            if (\in_array($field, self::NOISE, true) || ($entity instanceof TranslationInterface && \in_array($field, ['locale', 'translatable'], true))) {
                continue;
            }
            if ($new instanceof Collection || $old instanceof Collection) {
                continue;
            }
            $secret = \in_array($field, self::SECRETS, true);
            $changes[$prefix.$field] = [$secret && null !== $old ? '***' : $this->normalize($old), $secret && null !== $new ? '***' : $this->normalize($new)];
        }

        $this->pending[$key] ??= ['subject' => $subject, 'action' => 'update', 'changes' => []];
        $this->pending[$key]['changes'] = [...$this->pending[$key]['changes'], ...$changes];

        if ($uow->isScheduledForInsert($subject)) {
            $this->pending[$key]['action'] = 'create';
        } elseif ($uow->isScheduledForDelete($subject)) {
            $this->pending[$key]['action'] = 'delete';
        } elseif ($subject instanceof PublishableInterface && isset($changes['status'])) {
            $this->pending[$key]['action'] = $this->statusAction($changes['status'][1]);
        }
    }

    private function statusAction(mixed $newStatus): string
    {
        return match ($newStatus) {
            PublicationStatus::Published->value => 'publish',
            PublicationStatus::InReview->value => 'submit',
            PublicationStatus::Archived->value => 'archive',
            default => 'unpublish',
        };
    }

    private function isAudited(object $subject, string $operation): bool
    {
        if ($subject instanceof ServiceRequest) {
            return 'update' === $operation;
        }

        return $subject instanceof PublishableInterface
            || $subject instanceof User
            || $subject instanceof Setting
            || $subject instanceof MenuItem
            || $subject instanceof Category
            || $subject instanceof Tag
            || $subject instanceof Media;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }
        if (\is_object($value)) {
            return (new \ReflectionClass($value))->getShortName().'#'.($this->id($value) ?? '?');
        }
        if (\is_string($value) && mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            return mb_substr($value, 0, self::MAX_VALUE_LENGTH).'…';
        }

        return $value;
    }

    private function id(object $subject): ?int
    {
        $id = method_exists($subject, 'getId') ? $subject->getId() : null;

        return \is_int($id) ? $id : null;
    }

    private function label(object $subject): ?string
    {
        $label = null;
        if ($subject instanceof User) {
            $label = $subject->getEmail();
        } elseif ($subject instanceof Setting) {
            $label = $subject->getName();
        } elseif ($subject instanceof ServiceRequest) {
            $label = $subject->getTrackingNumber();
        } elseif (method_exists($subject, 'getTranslation')) {
            $translation = $subject->getTranslation('fr') ?? ($subject->getTranslations()->first() ?: null);
            $label = \is_object($translation) && method_exists($translation, 'getTitle') ? $translation->getTitle() : null;
            $label ??= \is_object($translation) && method_exists($translation, 'getLabel') ? $translation->getLabel() : null;
        }

        return null === $label ? null : mb_substr((string) $label, 0, 255);
    }
}
