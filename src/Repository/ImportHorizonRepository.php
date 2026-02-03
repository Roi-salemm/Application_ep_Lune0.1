<?php

/**
 * Depot des imports Horizons.
 * Pourquoi: paginer et retrouver les runs Moon/Sun par periode pour le parse.
 * Infos: les tris par defaut restent sur la periode de run.
 */

namespace App\Repository;

use App\Entity\ImportHorizon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportHorizon>
 */
class ImportHorizonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportHorizon::class);
    }

    /**
     * @return ImportHorizon[]
     */
    public function findPage(int $page, int $limit, string $sort = 'period', string $direction = 'asc'): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sort = strtolower($sort);

        $query = $this->createQueryBuilder('m');

        if ($sort === 'id') {
            $query->orderBy('m.id', $direction);
        } else {
            $query
                ->orderBy('m.start_utc', $direction)
                ->addOrderBy('m.stop_utc', $direction)
                ->addOrderBy('m.id', $direction);
        }

        return $query
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestByTargetAndPeriod(
        string $target,
        \DateTimeInterface $start,
        \DateTimeInterface $stop,
        ?string $center = null,
        ?string $step = null
    ): ?ImportHorizon {
        $query = $this->createQueryBuilder('m')
            ->andWhere('m.target = :target')
            ->andWhere('m.start_utc <= :start')
            ->andWhere('m.stop_utc >= :stop')
            ->setParameter('target', $target)
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(1);

        if ($center !== null && $center !== '') {
            $query->andWhere('m.center = :center')->setParameter('center', $center);
        }
        if ($step !== null && $step !== '') {
            $query->andWhere('m.step_size = :step')->setParameter('step', $step);
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
