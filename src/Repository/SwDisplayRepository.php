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
}
