<?php

/**
 * Gere l'acces aux enregistrements moon_ephemeris_hour.
 * Pourquoi: centraliser les requetes (pagination, couverture, comptages).
 * Infos: les requetes admin utilisent un tri par ts_utc desc.
 */

namespace App\Repository;

use App\Entity\MoonEphemerisHour;
use App\Entity\ImportHorizon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MoonEphemerisHour>
 */
class MoonEphemerisHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoonEphemerisHour::class);
    }

    /**
     * @return MoonEphemerisHour[]
     */
    public function findLatest(int $limit): array
    {
        return $this->findBy([], ['ts_utc' => 'DESC'], $limit);
    }

    /**
     * @return MoonEphemerisHour[]
     */
    public function findLatestPaged(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.ts_utc', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
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

    /**
     * @return array<string, MoonEphemerisHour>
     */
    public function findByRunIndexedByTimestamp(int $runId): array
    {
        $rows = $this->findBy([
            'run_id' => $runId,
        ]);

        $indexed = [];
        foreach ($rows as $row) {
            $ts = $row->getTsUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }
            $indexed[$ts->format('Y-m-d H:i')] = $row;
        }

        return $indexed;
    }

    public function deleteByRun(int $runId): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.run_id = :run')
            ->setParameter('run', $runId)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<string, MoonEphemerisHour>
     */
    public function findByTimestampRangeIndexed(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        $rows = $this->createQueryBuilder('m')
            ->andWhere('m.ts_utc >= :start')
            ->andWhere('m.ts_utc <= :stop')
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $ts = $row->getTsUtc();
            if (!$ts instanceof \DateTimeInterface) {
                continue;
            }
            $indexed[$ts->format('Y-m-d H:i')] = $row;
        }

        return $indexed;
    }

    /**
     * @return MoonEphemerisHour[]
     */
    public function findByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.ts_utc >= :start')
            ->andWhere('m.ts_utc <= :stop')
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->orderBy('m.ts_utc', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.ts_utc >= :start')
            ->andWhere('m.ts_utc < :stop')
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return string[]
     */
    public function findMonthCoverage(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $rows = $connection->fetchFirstColumn('
            SELECT DATE_FORMAT(ts_utc, "%Y-%m") AS month_key
            FROM moon_ephemeris_hour
            WHERE ts_utc IS NOT NULL
            GROUP BY month_key
            ORDER BY month_key
        ');

        return array_values(array_filter($rows));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $connection = $this->getEntityManager()->getConnection();
        $value = $connection->fetchOne('SELECT MAX(ts_utc) FROM moon_ephemeris_hour');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }

    public function findLatestAtOrBefore(\DateTimeInterface $timestamp): ?MoonEphemerisHour
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.ts_utc <= :ts')
            ->setParameter('ts', $timestamp)
            ->orderBy('m.ts_utc', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return MoonEphemerisHour[] Returns an array of MoonEphemerisHour objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MoonEphemerisHour
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
