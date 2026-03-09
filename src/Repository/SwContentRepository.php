<?php

namespace App\Repository;

use App\Entity\SwContent;
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
}
