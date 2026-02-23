<?php

namespace App\Repository;

use App\Entity\IaAdminHistorique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class IaAdminHistoriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IaAdminHistorique::class);
    }

    public function findRecentDuplicate(string $fingerprint, int $secondsWindow = 10): ?IaAdminHistorique
    {
        $since = new \DateTimeImmutable(sprintf('-%d seconds', $secondsWindow));

        return $this->createQueryBuilder('h')
            ->andWhere('h.fingerprint = :fp')
            ->andWhere('h.createdAt >= :since')
            ->setParameter('fp', $fingerprint)
            ->setParameter('since', $since)
            ->orderBy('h.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}