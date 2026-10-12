<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Enum\AdminRole;
use OTPHP\TOTP;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Utilisateurs d'administration de démonstration et de test. Mot de passe commun : « mot-de-passe-de-test ».
 * Par défaut le compte a déjà enrôlé la double authentification (secret fixe) ; withoutTwoFactor() simule un premier accès.
 *
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string PASSWORD = 'mot-de-passe-de-test';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    /** Secret TOTP du compte de test : permet de calculer le code courant dans les tests. */
    public static function totpSecret(): string
    {
        return 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    }

    public static function currentCode(): string
    {
        return TOTP::createFromSecret(self::totpSecret())->now();
    }

    public function withRole(AdminRole ...$roles): static
    {
        return $this->with(['roles' => array_map(static fn (AdminRole $r): string => $r->value, $roles)]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->with(['totpSecret' => null]);
    }

    public function inactive(): static
    {
        return $this->with(['active' => false]);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->userName().'@example.org',
            'name' => self::faker()->name(),
            'roles' => [AdminRole::Editor->value],
            'active' => true,
            'totpSecret' => self::totpSecret(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        });
    }
}
