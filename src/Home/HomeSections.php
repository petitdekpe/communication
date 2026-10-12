<?php

declare(strict_types=1);

namespace App\Home;

use App\Entity\Article;
use App\Entity\Consultation;
use App\Entity\Event;
use App\Entity\KeyFigure;
use App\Entity\OfficialDocument;
use App\Entity\Service;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\ConsultationRepository;
use App\Repository\EventRepository;
use App\Repository\KeyFigureRepository;
use App\Repository\OfficialDocumentRepository;
use App\Repository\ServiceRepository;
use Psr\Clock\ClockInterface;

/**
 * Contenus publiés affichés sur l'accueil. Seuls les contenus publiés à la date courante sont renvoyés.
 */
final class HomeSections
{
    private const int RELEASES = 3;
    private const int NEWS = 3;
    private const int DOCUMENTS = 3;
    private const int EVENTS = 3;
    private const int SERVICES = 4;
    private const int FIGURES = 4;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly OfficialDocumentRepository $documents,
        private readonly EventRepository $events,
        private readonly ServiceRepository $services,
        private readonly KeyFigureRepository $keyFigures,
        private readonly ConsultationRepository $consultations,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Article à la une. */
    public function featured(): ?Article
    {
        return $this->articles->findFeatured($this->clock->now());
    }

    /** @return list<Article> */
    public function releases(): array
    {
        return $this->articles->findLatestOfType(ArticleType::Communique, self::RELEASES, $this->clock->now());
    }

    /** @return list<Article> */
    public function news(): array
    {
        return $this->articles->findLatestNews(self::NEWS, $this->clock->now());
    }

    /** @return list<OfficialDocument> */
    public function documents(): array
    {
        return $this->documents->findLatest(self::DOCUMENTS, $this->clock->now());
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->events->findUpcoming(self::EVENTS, $this->clock->now());
    }

    /** @return list<Service> */
    public function services(): array
    {
        return \array_slice($this->services->findPublished($this->clock->now()), 0, self::SERVICES);
    }

    /** @return list<KeyFigure> */
    public function figures(): array
    {
        return $this->keyFigures->findPublished($this->clock->now(), self::FIGURES);
    }

    /** Consultation citoyenne mise en avant (ouverte, sinon la plus récente), ou null. */
    public function consultation(): ?Consultation
    {
        return $this->consultations->findHighlight($this->clock->now());
    }
}
