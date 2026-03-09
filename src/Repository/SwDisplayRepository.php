<?php

namespace App\Repository;

use App\Entity\SwDisplay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des definitions d affichage sw_display.
 * Pourquoi: centraliser les requetes sur l identite metier des affichages.
 * Info: base volontairement simple, extensible pour les filtres famille/mode/statut.
 *
 * @extends ServiceEntityRepository<SwDisplay>
 */
class SwDisplayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwDisplay::class);
    }

    public function findOneByCode(string $code, string $family = 'symbolic', string $lang = 'fr'): ?SwDisplay
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.code = :code')
            ->andWhere('d.family = :family')
            ->andWhere('d.lang = :lang')
            ->setParameter('code', $code)
            ->setParameter('family', $family)
            ->setParameter('lang', $lang)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $codes
     * @return array<string, SwDisplay>
     */
    public function findByCodesIndexed(array $codes, string $family = 'symbolic', string $lang = 'fr'): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->andWhere('d.code IN (:codes)')
            ->andWhere('d.family = :family')
            ->andWhere('d.lang = :lang')
            ->setParameter('codes', $codes)
            ->setParameter('family', $family)
            ->setParameter('lang', $lang)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            if (!$row instanceof SwDisplay) {
                continue;
            }
            $indexed[$row->getCode()] = $row;
        }

        return $indexed;
    }
}
