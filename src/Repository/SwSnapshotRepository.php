<?php

namespace App\Repository;

use App\Entity\SwSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository de projection snapshot.
 * Pourquoi: exposer des acces simples pour l admin et la synchronisation automatique.
 * Info: la cle metier de projection est sw_schedule_id (une ligne snapshot par texte).
 *
 * @extends ServiceEntityRepository<SwSnapshot>
 */
class SwSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwSnapshot::class);
    }

    /**
     * @return SwSnapshot[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('snap')
            ->addSelect('d', 'c', 's')
            ->innerJoin('snap.swDisplay', 'd')
            ->innerJoin('snap.swContent', 'c')
            ->innerJoin('snap.swSchedule', 's')
            ->orderBy('snap.startsAt', 'ASC')
            ->addOrderBy('snap.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByScheduleId(string $scheduleId): ?SwSnapshot
    {
        return $this->createQueryBuilder('snap')
            ->innerJoin('snap.swSchedule', 's')
            ->andWhere('s.id = :scheduleId')
            ->setParameter('scheduleId', $scheduleId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return SwSnapshot[]
     */
    public function findByDisplayId(string $displayId): array
    {
        return $this->createQueryBuilder('snap')
            ->innerJoin('snap.swDisplay', 'd')
            ->andWhere('d.id = :displayId')
            ->setParameter('displayId', $displayId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les IDs de schedules presents en snapshot.
     *
     * @param string[] $scheduleIds
     * @return string[]
     */
    public function findScheduleIdsIn(array $scheduleIds): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => trim((string) $id), $scheduleIds),
            static fn (string $id): bool => $id !== ''
        )));
        if ($normalized === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('snap')
            ->select('IDENTITY(snap.swSchedule) AS schedule_id')
            ->andWhere('IDENTITY(snap.swSchedule) IN (:scheduleIds)')
            ->setParameter('scheduleIds', $normalized)
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['schedule_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $result[] = $id;
        }

        return array_values(array_unique($result));
    }
}
