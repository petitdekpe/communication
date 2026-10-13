<?php

declare(strict_types=1);

namespace App\Cache;

use App\Entity\AuditLog;
use App\Entity\ConsultationContribution;
use App\Entity\ContentVersion;
use App\Entity\EditLock;
use App\Entity\NewsletterSubscriber;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\PublicationHistory;
use App\Entity\ResetPasswordRequest;
use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestAttachment;
use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;

/**
 * Invalide les caches de pages (HTML et blocs dynamiques) dès qu'un enregistrement modifie ce que le public voit :
 * publication, dépublication, programmation ou modification d'un contenu, d'une page, d'un bloc global, d'un menu ou d'un
 * paramètre. Les étiquettes sont calculées au flush, l'invalidation a lieu une fois les données écrites : la page
 * recalculée par la requête suivante est donc à jour.
 *
 * Toute publication passe par un flush (workflow, administration, Scheduler pour la publication programmée) : un seul point
 * suffit, sans écouter chaque transition.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PageCacheInvalidator
{
    /** Entités sans effet sur le site public (journaux, comptes, demandes et abonnés, verrous d'édition, historiques). */
    private const array IGNORED = [
        AuditLog::class, ContentVersion::class, EditLock::class, PublicationHistory::class, User::class, ResetPasswordRequest::class,
        ServiceRequest::class, ServiceRequestAttachment::class, ConsultationContribution::class, NewsletterSubscriber::class,
    ];

    /** Champs d'une page qui changent l'arborescence (fil d'Ariane, sous-pages, plan du site, adresses). */
    private const array TREE_FIELDS = ['parent', 'position', 'status', 'publishedAt', 'systemKey'];

    /** @var array<string, true> */
    private array $pending = [];

    public function __construct(
        private readonly ContentCache $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity, $uow, true);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity, $uow, false);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collect($entity, $uow, true);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }

        $tags = array_keys($this->pending);
        $this->pending = [];

        try {
            $this->cache->invalidate($tags);
        } catch (\Throwable $exception) {
            // Une panne du cache ne doit pas faire échouer l'enregistrement ; les durées de vie bornent l'obsolescence.
            $this->logger->error('Invalidation du cache de pages impossible : {message}', ['message' => $exception->getMessage()]);
        }
    }

    private function collect(object $entity, UnitOfWork $uow, bool $structural): void
    {
        if (\in_array($entity::class, self::IGNORED, true)) {
            return;
        }

        $tags = match (true) {
            $entity instanceof Page => $this->pageTags($entity, $structural || [] !== array_intersect(self::TREE_FIELDS, array_keys($uow->getEntityChangeSet($entity)))),
            // Le titre, le chemin et la description d'une page alimentent le fil d'Ariane, les sous-pages et le plan du site.
            $entity instanceof PageTranslation => [CacheTag::PAGES_TREE, ...($entity->getTranslatable()->getId() ? [CacheTag::page($entity->getTranslatable()->getId())] : [])],
            $entity instanceof Setting, $entity instanceof SettingTranslation => [CacheTag::SETTINGS],
            default => [CacheTag::CONTENT],
        };

        foreach ($tags as $tag) {
            $this->pending[$tag] = true;
        }
    }

    /**
     * @return list<string>
     */
    private function pageTags(Page $page, bool $structural): array
    {
        $tags = null === $page->getId() ? [] : [CacheTag::page($page->getId())];

        return $structural ? [...$tags, CacheTag::PAGES_TREE] : $tags;
    }
}
