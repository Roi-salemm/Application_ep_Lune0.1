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
     * @param string[] $readingModes
     * @return SwSchedule[]
     */
    public function findTimelineEntriesForAdmin(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        array $readingModes,
        string $family = 'symbolic',
        string $lang = 'fr'
    ): array {
        if ($readingModes === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->addSelect('d', 'c')
            ->innerJoin('s.display', 'd')
            ->innerJoin('s.content', 'c')
            ->andWhere('d.readingMode IN (:readingModes)')
            ->andWhere('d.family = :family')
            ->andWhere('d.lang = :lang')
            ->andWhere('s.startsAtUtc < :endUtc')
            ->andWhere('s.endsAtUtc > :startUtc')
            ->setParameter('readingModes', $readingModes)
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

    /**
     * Retourne les schedules Weather termines avant une date de reference.
     * Pourquoi: permettre la rotation coherente variant_no a partir du dernier historique en base.
     *
     * @return SwSchedule[]
     */
    public function findWeatherSchedulesEndingBefore(
        \DateTimeImmutable $beforeUtc,
        int $limit = 4000,
        string $family = 'symbolic',
        string $readingMode = 'weather',
        string $lang = 'fr'
    ): array {
        return $this->createQueryBuilder('s')
            ->addSelect('d', 'c')
            ->innerJoin('s.display', 'd')
            ->innerJoin('s.content', 'c')
            ->andWhere('d.family = :family')
            ->andWhere('d.readingMode = :readingMode')
            ->andWhere('d.lang = :lang')
            ->andWhere('s.endsAtUtc <= :beforeUtc')
            ->setParameter('family', $family)
            ->setParameter('readingMode', $readingMode)
            ->setParameter('lang', $lang)
            ->setParameter('beforeUtc', $beforeUtc)
            ->orderBy('s.endsAtUtc', 'DESC')
            ->addOrderBy('s.startsAtUtc', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
