<?php

namespace App\Repository;

use App\Entity\OrbWindow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des fenetres orb_window.
 *
 * @extends ServiceEntityRepository<OrbWindow>
 */
class OrbWindowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrbWindow::class);
    }
}

