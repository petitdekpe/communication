<?php

declare(strict_types=1);

namespace App\Admin\Export;

use App\Admin\AdminResource;
use App\Data\KeyFigureCsvExporter;
use App\Entity\ConsultationContribution;
use App\Entity\NewsletterSubscriber;
use App\Entity\ServiceRequest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Export CSV des « demandes reçues » (demandes et accréditations, contributions, inscrits à la lettre) sur une période.
 * Même format que l'export des chiffres clés : UTF-8 avec BOM pour Excel, séparateur « ; », cellules neutralisées contre
 * l'injection de formules. La période est exprimée en jours du fuseau d'affichage, bornes comprises.
 */
final class ResourceCsvExporter
{
    /** Champ date filtré par la période, selon la ressource. */
    private const array DATE_FIELDS = ['requests' => 'createdAt', 'contributions' => 'createdAt', 'subscribers' => 'subscribedAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%app.timezone%')]
        private readonly string $timezone,
    ) {
    }

    public function supports(AdminResource $resource): bool
    {
        return isset(self::DATE_FIELDS[$resource->id]);
    }

    /**
     * @param resource $output
     *
     * @return int nombre de lignes de données écrites
     */
    public function write(AdminResource $resource, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, $output): int
    {
        $field = self::DATE_FIELDS[$resource->id] ?? throw new \InvalidArgumentException(\sprintf('Export non prévu pour %s.', $resource->id));

        $query = $this->entityManager->createQueryBuilder()->select('e')->from($resource->entity, 'e')->orderBy('e.'.$field, 'ASC')->addOrderBy('e.id', 'ASC');
        $zone = new \DateTimeZone($this->timezone);
        $utc = new \DateTimeZone('UTC');
        if (null !== $from) {
            $query->andWhere('e.'.$field.' >= :from')->setParameter('from', $from->setTimezone($zone)->setTime(0, 0)->setTimezone($utc));
        }
        if (null !== $to) {
            $query->andWhere('e.'.$field.' < :to')->setParameter('to', $to->setTimezone($zone)->setTime(0, 0)->modify('+1 day')->setTimezone($utc));
        }

        fwrite($output, "\xEF\xBB\xBF");
        $count = 0;
        $payloadKeys = 'requests' === $resource->id ? $this->payloadKeys(clone $query) : [];
        $this->row($output, $this->header($resource->id, $payloadKeys));

        /** @var iterable<object> $entities */
        $entities = $query->getQuery()->toIterable();
        foreach ($entities as $entity) {
            $row = match (true) {
                $entity instanceof ServiceRequest => $this->requestRow($entity, $payloadKeys),
                $entity instanceof ConsultationContribution => $this->contributionRow($entity),
                $entity instanceof NewsletterSubscriber => $this->subscriberRow($entity),
                default => null,
            };
            if (null !== $row) {
                $this->row($output, $row);
                ++$count;
            }
            $this->entityManager->detach($entity);
        }

        return $count;
    }

    /**
     * @param list<string> $payloadKeys
     *
     * @return list<string>
     */
    private function header(string $resourceId, array $payloadKeys): array
    {
        return match ($resourceId) {
            'requests' => ['tracking_number', 'service', 'status', 'received_at', 'email', 'language', 'consented_at', ...$payloadKeys, 'internal_note'],
            'contributions' => ['id', 'consultation', 'name', 'organization', 'text', 'received_at', 'consented_at', 'file'],
            default => ['email', 'language', 'status', 'subscribed_at', 'confirmed_at', 'unsubscribed_at'],
        };
    }

    /**
     * Champs du formulaire présents dans les demandes de la période (un formulaire peut en avoir d'autres qu'un autre).
     *
     * @return list<string>
     */
    private function payloadKeys(QueryBuilder $query): array
    {
        $keys = [];
        /** @var list<array{payload: array<string, mixed>}> $rows */
        $rows = $query->select('e.payload')->resetDQLPart('orderBy')->getQuery()->getArrayResult();
        foreach ($rows as $row) {
            foreach (array_keys($row['payload']) as $key) {
                $keys[(string) $key] = true;
            }
        }
        $names = array_keys($keys);
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $payloadKeys
     *
     * @return list<string>
     */
    private function requestRow(ServiceRequest $request, array $payloadKeys): array
    {
        $payload = $request->getPayload();
        $values = array_map(fn (string $key): string => $this->scalar($payload[$key] ?? null), $payloadKeys);

        return [
            self::safe($request->getTrackingNumber()),
            self::safe((string) $request->getService()->translate('fr')->getTitle()),
            $request->getStatus()->value,
            $this->date($request->getCreatedAt()),
            self::safe($request->getEmail()),
            $request->getLocale(),
            $this->date($request->getConsentedAt()),
            ...$values,
            self::safe((string) $request->getInternalNote()),
        ];
    }

    /**
     * @return list<string>
     */
    private function contributionRow(ConsultationContribution $contribution): array
    {
        return [
            (string) $contribution->getId(),
            self::safe((string) $contribution->getConsultation()->translate('fr')->getTitle()),
            self::safe($contribution->getName()),
            self::safe((string) $contribution->getOrganization()),
            self::safe($contribution->getText()),
            $this->date($contribution->getCreatedAt()),
            $this->date($contribution->getConsentedAt()),
            self::safe((string) $contribution->getFileOriginalName()),
        ];
    }

    /**
     * @return list<string>
     */
    private function subscriberRow(NewsletterSubscriber $subscriber): array
    {
        return [
            self::safe($subscriber->getEmail()),
            $subscriber->getLocale(),
            $subscriber->getStatus()->value,
            $this->date($subscriber->getSubscribedAt()),
            $this->date($subscriber->getConfirmedAt()),
            $this->date($subscriber->getUnsubscribedAt()),
        ];
    }

    private function scalar(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return self::safe(\is_scalar($value) ? (string) $value : (string) json_encode($value, \JSON_UNESCAPED_UNICODE));
    }

    private function date(?\DateTimeImmutable $date): string
    {
        return null === $date ? '' : $date->setTimezone(new \DateTimeZone($this->timezone))->format('Y-m-d H:i');
    }

    private static function safe(string $text): string
    {
        return KeyFigureCsvExporter::safe($text);
    }

    /**
     * @param resource     $output
     * @param list<string> $fields
     */
    private function row($output, array $fields): void
    {
        fputcsv($output, $fields, ';', '"', '\\', "\r\n");
    }
}
