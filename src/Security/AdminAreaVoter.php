<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ServiceRequest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits hors contenus : comptes et paramètres (super administrateur), journal d'audit et page d'accueil (administrateur),
 * traductions (traducteur),
 * demandes des usagers (rôle presse, et administrateur par héritage).
 *
 * @extends Voter<string, mixed>
 */
final class AdminAreaVoter extends Voter
{
    private const array ATTRIBUTES = [
        Permission::USER_MANAGE, Permission::SETTINGS_MANAGE, Permission::AUDIT_VIEW, Permission::HOME_MANAGE, Permission::TRANSLATION_MANAGE,
        Permission::REQUEST_VIEW, Permission::REQUEST_PROCESS,
    ];

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        if (\in_array($attribute, [Permission::REQUEST_VIEW, Permission::REQUEST_PROCESS], true)) {
            return null === $subject || $subject instanceof ServiceRequest || ServiceRequest::class === $subject;
        }

        return null === $subject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            Permission::USER_MANAGE, Permission::SETTINGS_MANAGE => $this->security->isGranted('ROLE_SUPER_ADMIN'),
            Permission::AUDIT_VIEW, Permission::HOME_MANAGE => $this->security->isGranted('ROLE_ADMIN'),
            Permission::TRANSLATION_MANAGE => $this->security->isGranted('ROLE_TRANSLATOR'),
            Permission::REQUEST_VIEW, Permission::REQUEST_PROCESS => $this->security->isGranted('ROLE_PRESS'),
            default => false,
        };
    }
}
