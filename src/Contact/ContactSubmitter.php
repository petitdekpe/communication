<?php

declare(strict_types=1);

namespace App\Contact;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Enregistre un message de contact, puis en avertit le Ministère.
 */
final class ContactSubmitter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContactMailer $mailer,
        private readonly ClockInterface $clock,
    ) {
    }

    public function submit(ContactData $data, string $locale): ContactMessage
    {
        $phone = trim((string) $data->phone);
        $message = new ContactMessage(
            trim((string) $data->name),
            trim((string) $data->email),
            '' === $phone ? null : $phone,
            trim((string) $data->subject),
            trim((string) $data->message),
            $locale,
            $this->clock->now(),
        );
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->mailer->notify($message);

        return $message;
    }
}
