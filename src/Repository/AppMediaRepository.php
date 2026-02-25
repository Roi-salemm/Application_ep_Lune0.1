<?php

namespace App\Repository;

use App\Entity\AppMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des medias de l application.
 * Pourquoi: centraliser les acces a app_media (images, audio, video, docs).
 * Info: repository de base, sans requetes custom.
 *
 * @extends ServiceEntityRepository<AppMedia>
 */
class AppMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppMedia::class);
    }
}
