<?php

declare(strict_types=1);

namespace App\Agenda;

use App\Entity\Event;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Génère un calendrier iCalendar (RFC 5545) : un VEVENT par événement.
 *
 * - Les heures sont écrites en UTC (suffixe Z) : aucun VTIMEZONE à embarquer, tous les clients convertissent.
 * - UID stable (« event-{id}@{hôte} ») : un client qui se réabonne met l'événement à jour au lieu de le dupliquer.
 * - Lignes terminées par CRLF, repliées à 75 octets sans couper un caractère UTF-8, textes échappés.
 */
final class IcalendarBuilder
{
    private const int LINE_OCTETS = 75;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param iterable<Event> $events
     */
    public function build(iterable $events, string $locale, string $calendarName): string
    {
        $stamp = $this->utc($this->clock->now());

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ministère de la Communication, chargé des Médias//Agenda//'.strtoupper($locale),
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($calendarName),
            'X-WR-TIMEZONE:UTC',
        ];

        foreach ($events as $event) {
            array_push($lines, ...$this->vevent($event, $locale, $stamp));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * @return list<string>
     */
    private function vevent(Event $event, string $locale, string $stamp): array
    {
        $translation = $event->translate($locale);
        $url = $this->urlGenerator->generate('app_event_show', ['_locale' => $translation->getLocale(), 'slug' => $translation->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $host = parse_url($url, \PHP_URL_HOST);

        $startsAt = $event->getStartsAt() ?? throw new \LogicException('Événement sans date de début.');
        $endsAt = $event->getEndsAt();

        $lines = [
            'BEGIN:VEVENT',
            \sprintf('UID:event-%d@%s', (int) $event->getId(), \is_string($host) ? $host : 'localhost'),
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$this->utc($startsAt),
        ];
        // DTEND doit être postérieur à DTSTART : une fin absente ou incohérente est omise (événement ponctuel).
        if (null !== $endsAt && $endsAt > $startsAt) {
            $lines[] = 'DTEND:'.$this->utc($endsAt);
        }
        if (null !== $event->getUpdatedAt()) {
            $lines[] = 'LAST-MODIFIED:'.$this->utc($event->getUpdatedAt());
        }

        $lines[] = 'SUMMARY:'.$this->escape((string) $translation->getTitle());
        if (null !== $translation->getLocation() && '' !== $translation->getLocation()) {
            $lines[] = 'LOCATION:'.$this->escape($translation->getLocation());
        }
        if (null !== $translation->getDescription() && '' !== $translation->getDescription()) {
            $lines[] = 'DESCRIPTION:'.$this->escape($translation->getDescription());
        }
        $lines[] = 'CATEGORIES:'.$this->escape($this->translator->trans($event->getType()->labelKey(), locale: $locale));
        $lines[] = 'URL:'.$url;
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function utc(\DateTimeInterface $date): string
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** Échappement des valeurs TEXT (RFC 5545, 3.3.11). */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Repli des lignes longues (RFC 5545, 3.1) : 75 octets au plus, suite précédée d'une espace. */
    private function fold(string $line): string
    {
        if (\strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $parts = [];
        $limit = self::LINE_OCTETS;
        while (\strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $chunk;
            $line = substr($line, \strlen($chunk));
            $limit = self::LINE_OCTETS - 1; // l'espace de continuation compte dans les 75 octets
        }
        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
