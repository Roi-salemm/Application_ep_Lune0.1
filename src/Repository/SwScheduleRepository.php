<?php

namespace App\Repository;

use App\Entity\SwSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des fenetres de diffusion sw_schedule.
 * Pourquoi: isoler la logique de publication temporelle et de priorite en UTC.
 * Info: les requetes de production pourront filtrer starts_at_utc/ends_at_utc + is_published.
 *
 * @extends ServiceEntityRepository<SwSchedule>
 */
class SwScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwSchedule::class);
    }
}
