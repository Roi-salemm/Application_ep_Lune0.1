<?php

namespace App\Repository;

use App\Entity\AppCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des cards (articles / cycles).
 * Pourquoi: centraliser les requetes sur le catalogue app_card.
 * Info: utilise les methodes standard Doctrine, sans logique custom pour l instant.
 *
 * @extends ServiceEntityRepository<AppCard>
 */
class AppCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppCard::class);
    }
}
