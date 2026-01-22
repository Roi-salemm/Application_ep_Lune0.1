<?php

namespace App\Repository;

use App\Entity\MoonNasaImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MoonNasaImport>
 */
class MoonNasaImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoonNasaImport::class);
    }

    /**
     * @return MoonNasaImport[]
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

    //    /**
    //     * @return MoonNasaImport[] Returns an array of MoonNasaImport objects
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

    //    public function findOneBySomeField($value): ?MoonNasaImport
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
