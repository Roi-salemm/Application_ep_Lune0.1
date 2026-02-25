<?php

namespace App\Repository;

use App\Entity\AppCycleModuleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des items de module de cycle.
 * Pourquoi: factoriser les acces aux items ordonnes et filtres (preview, refs).
 * Info: aucun filtre custom pour l instant.
 *
 * @extends ServiceEntityRepository<AppCycleModuleItem>
 */
class AppCycleModuleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppCycleModuleItem::class);
    }
}
