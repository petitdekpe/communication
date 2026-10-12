<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TimestampableInterface;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\ContactMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message envoyé depuis le formulaire « Nous contacter » : nom, adresse électronique, téléphone facultatif, objet, texte.
 * Données personnelles, lues dans l'administration.
 */
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column]
    private \DateTimeImmutable $consentedAt;

    public function __construct(string $name, string $email, ?string $phone, string $subject, string $message, string $locale, \DateTimeImmutable $consentedAt)
    {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->subject = $subject;
        $this->message = $message;
        $this->locale = $locale;
        $this->consentedAt = $consentedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
    }
}
