<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use App\Repository\ServiceRequestRepository;
use Psr\Clock\ClockInterface;

/**
 * Numéro de suivi « MCM-2026-K7P3X9QD » : année puis 8 caractères aléatoires (alphabet sans I, L, O, U, 0, 1
 * pour éviter les confusions à la saisie). Non séquentiel : on ne peut pas deviner le numéro d'une autre demande.
 */
final class TrackingNumberGenerator
{
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
    private const int LENGTH = 8;

    public function __construct(
        private readonly ServiceRequestRepository $requests,
        private readonly ClockInterface $clock,
    ) {
    }

    public function generate(): string
    {
        do {
            $number = \sprintf('MCM-%s-%s', $this->clock->now()->format('Y'), $this->randomPart());
        } while ($this->requests->trackingNumberExists($number));

        return $number;
    }

    private function randomPart(): string
    {
        $part = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $part .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return $part;
    }
}
