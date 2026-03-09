<?php

namespace App\Repository;

use App\Entity\SwContent;
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

    /**
     * @param string[] $displayCodes
     * @return SwSchedule[]
     */
    public function findTimelineEntriesForAdmin(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        array $displayCodes,
        string $family = 'symbolic',
        string $lang = 'fr'
    ): array {
        if ($displayCodes === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->addSelect('d', 'c')
            ->innerJoin('s.display', 'd')
            ->innerJoin('s.content', 'c')
            ->andWhere('d.code IN (:codes)')
            ->andWhere('d.family = :family')
            ->andWhere('d.lang = :lang')
            ->andWhere('s.startsAtUtc < :endUtc')
            ->andWhere('s.endsAtUtc > :startUtc')
            ->setParameter('codes', $displayCodes)
            ->setParameter('family', $family)
            ->setParameter('lang', $lang)
            ->setParameter('startUtc', $startUtc)
            ->setParameter('endUtc', $endUtc)
            ->orderBy('s.startsAtUtc', 'ASC')
            ->addOrderBy('s.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByContent(SwContent $content): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.content = :content')
            ->setParameter('content', $content)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
