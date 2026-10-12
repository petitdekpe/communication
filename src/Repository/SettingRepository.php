<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, Setting> paramètres trouvés, par nom
     */
    public function findByNames(array $names): array
    {
        /** @var list<Setting> $settings */
        $settings = $this->createQueryBuilder('s')
            ->addSelect('t')
            ->leftJoin('s.translations', 't')
            ->andWhere('s.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        $byName = [];
        foreach ($settings as $setting) {
            $byName[(string) $setting->getName()] = $setting;
        }

        return $byName;
    }
}
