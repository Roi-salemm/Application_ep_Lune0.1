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
}

