<?php

namespace App\Repository;

use App\Entity\AppCycleModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des modules de cycle.
 * Pourquoi: regrouper les acces aux modules ordonnes d un cycle.
 * Info: repository simple, pourra accueillir des requetes custom plus tard.
 *
 * @extends ServiceEntityRepository<AppCycleModule>
 */
class AppCycleModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppCycleModule::class);
    }
}
