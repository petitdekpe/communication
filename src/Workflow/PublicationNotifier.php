<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Contract\PublishableInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * E-mails du circuit de publication : au relecteur quand un contenu est soumis, à l'auteur quand il est publié ou
 * refusé (motif joint). Les messages partent par Messenger (SendEmailMessage est routé sur le transport asynchrone).
 * Personne n'est notifié de sa propre action.
 */
final class PublicationNotifier
{
    private const string LOCALE = 'fr';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly ContentLabeler $labeler,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    #[AsEventListener(event: 'workflow.publication.completed')]
    public function onCompleted(CompletedEvent $event): void
    {
        $content = $event->getSubject();
        $name = $event->getTransition()?->getName();
        if (!$content instanceof PublishableInterface || null === $name) {
            return;
        }

        $reason = $event->getContext()['reason'] ?? null;
        $reason = \is_string($reason) ? $reason : null;

        match ($name) {
            'submit' => $this->notify($this->reviewers(), 'submitted', $content, null),
            'publish' => $this->notify($this->author($content), 'published', $content, null),
            'reject' => $this->notify($this->author($content), 'rejected', $content, $reason),
            default => null,
        };
    }

    /**
     * @param list<User> $recipients
     */
    private function notify(array $recipients, string $kind, PublishableInterface $content, ?string $reason): void
    {
        $actor = $this->security->getUser()?->getUserIdentifier();
        $title = $this->labeler->label($content, self::LOCALE);

        foreach ($recipients as $recipient) {
            if ($recipient->getUserIdentifier() === $actor) {
                continue;
            }
            $this->mailer->send((new TemplatedEmail())
                ->from($this->from)
                ->to(new Address($recipient->getUserIdentifier(), (string) $recipient->getName()))
                ->subject($this->translator->trans('workflow.email.'.$kind.'.subject', ['title' => $title], null, self::LOCALE))
                ->textTemplate('email/workflow.txt.twig')
                ->htmlTemplate('email/workflow.html.twig')
                ->locale(self::LOCALE)
                ->context(['locale' => self::LOCALE, 'kind' => $kind, 'title' => $title, 'reason' => $reason, 'url' => $this->labeler->adminUrl($content), 'recipient' => $recipient->getName()]));
        }
    }

    /**
     * @return list<User>
     */
    private function reviewers(): array
    {
        return array_values(array_filter(
            $this->users->findAll(),
            fn (User $user): bool => $user->isActive() && \in_array('ROLE_REVIEWER', $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true),
        ));
    }

    /**
     * @return list<User>
     */
    private function author(PublishableInterface $content): array
    {
        $email = $content->getCreatedBy();
        $user = null === $email ? null : $this->users->findByEmail($email);

        return null !== $user && $user->isActive() ? [$user] : [];
    }
}
