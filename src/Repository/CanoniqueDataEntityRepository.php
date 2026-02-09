<?php

/**
 * Repository Doctrine pour canonique_data.
 * Pourquoi: requetes typees sur les donnees canoniques via ORM.
 * Infos: coexiste avec le repository SQL direct pour l'admin/API.
 */

namespace App\Repository;

use App\Entity\CanoniqueData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CanoniqueData>
 */
final class CanoniqueDataEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CanoniqueData::class);
    }
}
