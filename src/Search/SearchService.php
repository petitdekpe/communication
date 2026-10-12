<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Contract\SluggableInterface;
use App\Entity\Contract\TranslatableInterface;
use App\Entity\Contract\TranslationInterface;
use App\Entity\Page;
use App\Enum\PublicationStatus;
use App\Page\PageTree;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Recherche globale : articles, documents, démarches, événements et pages publiés, groupés par type.
 *
 * Les mots sont cherchés (LIKE, sans distinction de casse ni d'accents selon la collation utf8mb4_unicode_ci)
 * dans la langue de la page et en français : un contenu qui n'existe qu'en français reste trouvable sous /en.
 * Les caractères joker (% et _) saisis par l'internaute sont échappés : ils sont cherchés tels quels.
 */
final class SearchService
{
    public const int MIN_LENGTH = 2;
    public const int MAX_LENGTH = 100;
    public const int PER_TYPE = 10;

    private readonly PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PageTree $pageTree,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Requête saisie, nettoyée : espaces superflus retirés, longueur bornée.
     */
    public static function normalize(string $query): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $query)), 0, self::MAX_LENGTH);
    }

    /**
     * @return array<string, list<SearchResult>> résultats par type (clé : valeur de SearchType), types sans résultat omis
     */
    public function search(string $query, string $locale, \DateTimeImmutable $now): array
    {
        $query = self::normalize($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $grouped = [];
        foreach (SearchType::cases() as $type) {
            $results = $this->searchType($type, $query, $locale, $now);
            if ([] !== $results) {
                $grouped[$type->value] = $results;
            }
        }

        return $grouped;
    }

    /**
     * @return list<SearchResult>
     */
    private function searchType(SearchType $type, string $query, string $locale, \DateTimeImmutable $now): array
    {
        $conditions = array_map(static fn (string $field): string => \sprintf("t.%s LIKE :q ESCAPE '!'", $field), $type->searchFields());
        $dateField = $type->dateField();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('t', 'p')
            ->from($type->translationEntity(), 't')
            ->innerJoin('t.translatable', 'p')
            ->andWhere('t.locale IN (:locales)')
            ->andWhere('('.implode(' OR ', $conditions).')')
            ->andWhere('p.status = :published')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('locales', array_unique([$locale, TranslatableInterface::DEFAULT_LOCALE]))
            ->setParameter('q', '%'.strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']).'%')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('p.'.$dateField, 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(self::PER_TYPE * 3);

        if (SearchType::Document === $type) {
            // Un document sans fichier n'est pas téléchargeable : il n'est pas public.
            $qb->andWhere('p.file IS NOT NULL');
        }

        /** @var list<TranslationInterface> $translations */
        $translations = $qb->getQuery()->getResult();

        // Un seul résultat par contenu, dans la langue de la page de préférence.
        $byContent = [];
        foreach ($translations as $translation) {
            $parent = $translation->getTranslatable();
            $key = spl_object_id($parent);
            if (!isset($byContent[$key]) || $translation->getLocale() === $locale) {
                $byContent[$key] = $translation;
            }
        }

        $results = [];
        foreach (\array_slice(array_values($byContent), 0, self::PER_TYPE) as $translation) {
            $results[] = $this->result($type, $translation, $locale);
        }

        return $results;
    }

    private function result(SearchType $type, TranslationInterface $translation, string $locale): SearchResult
    {
        $parent = $translation->getTranslatable();
        $snippet = $this->accessor->getValue($translation, $type->snippetField());
        $date = $this->accessor->getValue($parent, $type->dateField());

        return new SearchResult(
            $type,
            (string) $this->accessor->getValue($translation, 'title'),
            $this->url($type, $parent, $translation, $locale),
            \is_string($snippet) && '' !== trim($snippet) ? $snippet : null,
            $date instanceof \DateTimeImmutable ? $date : null,
            $translation->getLocale(),
        );
    }

    private function url(SearchType $type, TranslatableInterface $parent, TranslationInterface $translation, string $locale): string
    {
        $slug = $translation instanceof SluggableInterface ? $translation->getSlug() : null;
        $id = $this->accessor->getValue($parent, 'id');

        return match ($type) {
            SearchType::Article => $this->urlGenerator->generate('app_article_show', ['_locale' => $locale, 'slug' => $slug]),
            SearchType::Document => $this->urlGenerator->generate('app_document_download', ['_locale' => $locale, 'id' => $id]),
            SearchType::Service => $this->urlGenerator->generate('app_service_show', ['_locale' => $locale, 'slug' => $slug]),
            SearchType::Event => $this->urlGenerator->generate('app_event_show', ['_locale' => $locale, 'slug' => $slug]),
            SearchType::Page => $parent instanceof Page ? $this->pageTree->url($parent, $locale) : $this->urlGenerator->generate('app_home', ['_locale' => $locale]),
        };
    }
}
