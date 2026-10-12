<?php

declare(strict_types=1);

namespace App\Repository;

use App\Document\DocumentFilters;
use App\Entity\OfficialDocument;
use App\Entity\OfficialDocumentTranslation;
use App\Enum\PublicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Seuls les documents publiés (date de publication passée) et munis d'un fichier sont publics.
 *
 * @extends ServiceEntityRepository<OfficialDocument>
 */
class OfficialDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficialDocument::class);
    }

    /**
     * Page de la bibliothèque. Les mots-clés cherchent (FULLTEXT titre + résumé) dans la langue de la page
     * et en français : un document n'existant qu'en français reste trouvable sous /en.
     *
     * @return Paginator<OfficialDocument>
     */
    public function paginate(DocumentFilters $filters, int $page, int $perPage, \DateTimeImmutable $now): Paginator
    {
        $direction = $filters->newestFirst ? 'DESC' : 'ASC';
        $qb = $this->published($now)
            ->addSelect('translations', 'file')
            ->leftJoin('d.translations', 'translations')
            ->orderBy('d.documentDate', $direction)
            ->addOrderBy('d.id', $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $filters->type) {
            $qb->andWhere('d.type = :type')->setParameter('type', $filters->type);
        }
        if (null !== $filters->from) {
            $qb->andWhere('d.documentDate >= :from')->setParameter('from', $filters->from);
        }
        if (null !== $filters->to) {
            $qb->andWhere('d.documentDate <= :to')->setParameter('to', $filters->to);
        }
        if ($filters->matchesNothing()) {
            $qb->andWhere('1 = 0');
        } elseif (null !== $filters->match) {
            $qb->andWhere(\sprintf(
                'd.id IN (SELECT IDENTITY(st.translatable) FROM %s st WHERE st.locale IN (:locales) AND MATCH_AGAINST(st.title, st.summary, :match) > 0)',
                OfficialDocumentTranslation::class,
            ))
                ->setParameter('locales', array_unique([$filters->locale, 'fr']))
                ->setParameter('match', $filters->match);
        }

        return new Paginator($qb, fetchJoinCollection: true);
    }

    /**
     * Derniers documents signés (bloc « Documents officiels » de l'accueil).
     *
     * @return list<OfficialDocument>
     */
    public function findLatest(int $limit, \DateTimeImmutable $now, ?int $categoryId = null): array
    {
        $qb = $this->published($now)
            ->addSelect('translations', 'file')
            ->leftJoin('d.translations', 'translations')
            ->orderBy('d.documentDate', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $categoryId) {
            $qb->andWhere('IDENTITY(d.category) = :category')->setParameter('category', $categoryId);
        }

        // Paginator : la limite porte sur les documents, pas sur les lignes jointes (une par traduction).
        /** @var list<OfficialDocument> $documents */
        $documents = iterator_to_array(new Paginator($qb, fetchJoinCollection: true), false);

        return $documents;
    }

    public function findPublished(int $id, \DateTimeImmutable $now): ?OfficialDocument
    {
        $document = $this->published($now)->andWhere('d.id = :id')->setParameter('id', $id)->getQuery()->getOneOrNullResult();

        return $document instanceof OfficialDocument ? $document : null;
    }

    /**
     * Incrémentation atomique côté SQL : deux téléchargements simultanés comptent tous les deux.
     */
    public function incrementDownloadCount(OfficialDocument $document): void
    {
        $this->createQueryBuilder('d')
            ->update()
            ->set('d.downloadCount', 'd.downloadCount + 1')
            ->where('d = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->execute();
    }

    private function published(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.file', 'file')
            ->andWhere('d.status = :published')
            ->andWhere('d.publishedAt <= :now')
            ->setParameter('published', PublicationStatus::Published)
            ->setParameter('now', $now);
    }
}
