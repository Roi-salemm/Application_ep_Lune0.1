<?php

namespace App\Repository;

use App\Entity\SwContent;
use App\Entity\SwDisplay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des versions editoriales sw_content.
 * Pourquoi: concentrer les requetes sur version, validation et statut du contenu.
 * Info: prepare le terrain pour les recherches du contenu courant par affichage.
 *
 * @extends ServiceEntityRepository<SwContent>
 */
class SwContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwContent::class);
    }

    public function findMaxVersionNoForDisplay(SwDisplay $display): int
    {
        $value = $this->createQueryBuilder('c')
            ->select('MAX(c.versionNo)')
            ->andWhere('c.display = :display')
            ->setParameter('display', $display)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($value ?? 0);
    }

    public function clearCurrentForDisplay(SwDisplay $display): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isCurrent', ':isCurrent')
            ->andWhere('c.display = :display')
            ->andWhere('c.isCurrent = :currentOnly')
            ->setParameter('isCurrent', false)
            ->setParameter('display', $display)
            ->setParameter('currentOnly', true)
            ->getQuery()
            ->execute();
    }
}
