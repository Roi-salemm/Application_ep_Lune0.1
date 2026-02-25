<?php

namespace App\Repository;

use App\Entity\AppArticleContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository du contenu d articles.
 * Pourquoi: encapsuler les acces a app_article_content.
 * Info: repository simple sans requetes custom pour l instant.
 *
 * @extends ServiceEntityRepository<AppArticleContent>
 */
class AppArticleContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppArticleContent::class);
    }
}
