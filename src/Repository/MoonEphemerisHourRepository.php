<?php

namespace App\Repository;

use App\Entity\MoonEphemerisHour;
use App\Entity\MoonNasaImport;
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
     * @return array<string, MoonEphemerisHour>
     */
    public function findByRunIndexedByTimestamp(MoonNasaImport $run): array
    {
        $rows = $this->findBy([
            'run_id' => $run,
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

    public function deleteByRun(MoonNasaImport $run): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.run_id = :run')
            ->setParameter('run', $run)
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
