<?php

namespace App\Repository;

use App\Entity\SolarEphemerisHour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SolarEphemerisHour>
 */
class SolarEphemerisHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SolarEphemerisHour::class);
    }

    /**
     * @return SolarEphemerisHour[]
     */
    public function findLatest(int $limit): array
    {
        return $this->findBy([], ['ts_utc' => 'DESC'], $limit);
    }

    /**
     * @return array<string, SolarEphemerisHour>
     */
    public function findByTimestampRangeIndexed(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.ts_utc >= :start')
            ->andWhere('s.ts_utc <= :stop')
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
     * @return SolarEphemerisHour[]
     */
    public function findByTimestampRange(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.ts_utc >= :start')
            ->andWhere('s.ts_utc <= :stop')
            ->setParameter('start', $start)
            ->setParameter('stop', $stop)
            ->orderBy('s.ts_utc', 'ASC')
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
            FROM solar_ephemeris_hour
            WHERE ts_utc IS NOT NULL
            GROUP BY month_key
            ORDER BY month_key
        ');

        return array_values(array_filter($rows));
    }

    public function findMaxTimestamp(): ?\DateTimeImmutable
    {
        $connection = $this->getEntityManager()->getConnection();
        $value = $connection->fetchOne('SELECT MAX(ts_utc) FROM solar_ephemeris_hour');
        if (!$value) {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }
}
