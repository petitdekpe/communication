<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un compte désactivé ne peut plus se connecter, quel que soit son mot de passe. Le message est le même que pour
 * un identifiant inconnu côté formulaire (voir template) : on ne révèle pas l'existence du compte.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('admin.login.error');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
