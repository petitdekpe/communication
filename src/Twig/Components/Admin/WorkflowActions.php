<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Admin\AdminResource;
use App\Admin\ContentLocator;
use App\Entity\Contract\PublishableInterface;
use App\Entity\PublicationHistory;
use App\Lock\EditLockManager;
use App\Preview\PreviewLinks;
use App\Security\Permission;
use App\Version\VersionRecorder;
use App\Workflow\PublicationWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * En-tête de formulaire : état du contenu, transitions possibles (selon l'état et le droit, via le workflow et ses
 * Voters), publication programmée, lien d'aperçu signé, avertissement de verrou d'édition, versions et historique des
 * transitions (qui, quand, motif). Ouvrir le formulaire d'un contenu existant prend le verrou d'édition.
 *
 *   <twig:Admin:WorkflowActions :content="article" />
 */
#[AsTwigComponent]
final class WorkflowActions
{
    /** Contenu édité ; null pour un contenu pas encore enregistré. */
    public ?PublishableInterface $content = null;

    /** @var array{name: string, since: \DateTimeImmutable, expiresAt: \DateTimeImmutable}|false|null false = pas encore calculé */
    private array|false|null $lock = false;

    public function __construct(
        private readonly PublicationWorkflow $workflow,
        private readonly ContentLocator $locator,
        private readonly EntityManagerInterface $entityManager,
        private readonly PreviewLinks $previews,
        private readonly VersionRecorder $versions,
        private readonly EditLockManager $locks,
        private readonly Security $security,
    ) {
    }

    public function isSaved(): bool
    {
        return null !== $this->id();
    }

    public function resource(): ?AdminResource
    {
        return null === $this->content ? null : $this->locator->resourceFor($this->content);
    }

    public function id(): ?int
    {
        $id = null !== $this->content && method_exists($this->content, 'getId') ? $this->content->getId() : null;

        return \is_int($id) ? $id : null;
    }

    /**
     * @return list<string>
     */
    public function transitions(): array
    {
        return null === $this->content || !$this->isSaved() ? [] : $this->workflow->available($this->content);
    }

    public function canUnschedule(): bool
    {
        return null !== $this->content && null !== $this->content->getScheduledAt() && $this->security->isGranted(Permission::CONTENT_PUBLISH, $this->content);
    }

    /**
     * Verrou : détenteur autre que l'utilisateur courant, ou null (le verrou est pris pour l'utilisateur courant).
     *
     * @return array{name: string, since: \DateTimeImmutable, expiresAt: \DateTimeImmutable}|null
     */
    public function foreignLock(): ?array
    {
        if (false === $this->lock) {
            $user = $this->security->getUser();
            $this->lock = null !== $user && null !== $this->content && $this->canLock() ? $this->locks->acquire($this->content, $user) : null;
        }

        return $this->lock;
    }

    public function canLock(): bool
    {
        return null !== $this->content && $this->isSaved()
            && ($this->security->isGranted(Permission::CONTENT_EDIT, $this->content) || $this->security->isGranted(Permission::CONTENT_TRANSLATE, $this->content));
    }

    /**
     * Liens d'aperçu signés (24 h), un par langue, pour qui peut voir le contenu.
     *
     * @return array<string, string>
     */
    public function previewLinks(): array
    {
        if (null === $this->content || !$this->isSaved() || !$this->previews->supports($this->content) || !$this->security->isGranted(Permission::CONTENT_VIEW, $this->content)) {
            return [];
        }

        $links = [];
        foreach (['fr', 'en'] as $locale) {
            $links[$locale] = $this->previews->url($this->content, $locale);
        }

        return $links;
    }

    public function versionCount(): int
    {
        return null === $this->content || !$this->isSaved() ? 0 : \count($this->versions->history($this->content));
    }

    /**
     * @return list<PublicationHistory>
     */
    public function history(): array
    {
        if (null === $this->content || !$this->isSaved()) {
            return [];
        }

        return $this->entityManager->getRepository(PublicationHistory::class)->findBy(
            ['subjectClass' => $this->content::class, 'subjectId' => $this->id()],
            ['occurredAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function token(string $scope): string
    {
        $resource = $this->resource();

        return null === $resource || null === $this->id() ? '' : $this->locator->token($scope, $resource->id, $this->id());
    }
}
