<?php

namespace App\Repository;

use App\Entity\AiKnowledgeCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiKnowledgeCard>
 */
class AiKnowledgeCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiKnowledgeCard::class);
    }
}
