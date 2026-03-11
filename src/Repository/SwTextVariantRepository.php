<?php

namespace App\Repository;

use App\Entity\SwTextVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des variantes texte SW.
 * Pourquoi: centraliser le listing admin et les requetes de lecture rapide.
 *
 * @extends ServiceEntityRepository<SwTextVariant>
 */
class SwTextVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwTextVariant::class);
    }

    /**
     * @return SwTextVariant[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.sourceVariant', 's')
            ->addSelect('s')
            ->orderBy('v.updatedAtUtc', 'DESC')
            ->addOrderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les variantes activables pour le parse auto Weather.
     * Pourquoi: ne garder que les variantes validees/actives et les ordonner pour la rotation variant_no.
     *
     * @return SwTextVariant[]
     */
    public function findValidatedUsedWeatherVariants(
        string $family = 'symbolic',
        string $readingMode = 'SYM_Weather',
        string $lang = 'fr'
    ): array {
        return $this->createQueryBuilder('v')
            ->andWhere('v.family = :family')
            ->andWhere('v.readingMode = :readingMode')
            ->andWhere('v.lang = :lang')
            ->andWhere('v.isValidated = :isValidated')
            ->andWhere('v.isUsed = :isUsed')
            ->setParameter('family', $family)
            ->setParameter('readingMode', $readingMode)
            ->setParameter('lang', $lang)
            ->setParameter('isValidated', true)
            ->setParameter('isUsed', true)
            ->orderBy('v.phaseKey', 'ASC')
            ->addOrderBy('v.variantNo', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
