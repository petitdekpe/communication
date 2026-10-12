<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonction Twig de l'agenda : ligne d'informations d'un événement (nature et horaires), dans le fuseau
 * d'affichage du site et la langue de la page.
 */
final class AgendaExtension
{
    private readonly \DateTimeZone $siteZone;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        #[Autowire('%app.timezone%')]
        string $timezone,
    ) {
        $this->siteZone = new \DateTimeZone($timezone);
    }

    /**
     * Même jour : « Point de presse · 10:00 – 12:00 » ; plusieurs jours : « Sommet · 10:00 → 16 oct. 2026, 12:00 ».
     */
    #[AsTwigFunction('event_meta')]
    public function meta(Event $event): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
        $startsAt = $event->getStartsAt() ?? throw new \LogicException('Événement sans date de début.');
        $endsAt = $event->getEndsAt();

        $time = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT, $this->siteZone);
        $label = (string) $time->format($startsAt);

        if (null !== $endsAt && $endsAt > $startsAt) {
            $sameDay = $startsAt->setTimezone($this->siteZone)->format('Y-m-d') === $endsAt->setTimezone($this->siteZone)->format('Y-m-d');
            $label .= $sameDay
                ? ' – '.$time->format($endsAt)
                : ' → '.(new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $this->siteZone))->format($endsAt);
        }

        return $this->translator->trans($event->getType()->labelKey(), locale: $locale).' · '.$label;
    }
}
