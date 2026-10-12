<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AdminRole;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Utilisateur de l'administration. Mot de passe haché (jamais en clair) ; double authentification TOTP obligatoire :
 * tant que totpSecret est vide, l'utilisateur ne peut qu'enrôler son application d'authentification.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'admin_user')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'admin.user.email_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $name = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /** Mot de passe haché. */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /** Secret TOTP (base32). Vide tant que l'utilisateur n'a pas enrôlé son application. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** Aucun secret en clair n'est conservé sur l'objet : rien à effacer. */
    public function eraseCredentials(): void
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $allowed = array_column(AdminRole::cases(), 'value');
        $this->roles = array_values(array_intersect(array_unique($roles), $allowed));

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret && '' !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return $this->isTotpAuthenticationEnabled()
            ? new TotpConfiguration((string) $this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6)
            : null;
    }
}
