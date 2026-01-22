<?php

namespace App\Repository;

use App\Entity\MoonPhaseEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MoonPhaseEvent>
 */
class MoonPhaseEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoonPhaseEvent::class);
    }

    /**
     * @return MoonPhaseEvent[]
     */
    public function findLatest(int $limit): array
    {
        return $this->findBy([], ['ts_utc' => 'DESC'], $limit);
    }

    /**
     * @return array<string, MoonPhaseEvent>
     */
    public function findByTimestampRangeIndexed(\DateTimeInterface $start, \DateTimeInterface $stop): array
    {
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.ts_utc >= :start')
            ->andWhere('e.ts_utc <= :stop')
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
            $key = $row->getEventType() . '|' . $ts->format('Y-m-d H:i');
            $indexed[$key] = $row;
        }

        return $indexed;
    }
}
