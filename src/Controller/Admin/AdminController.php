<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;





final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_horizon_data');
    }
}


// (Test : voir le message ) 





// On va continuer sur les modifs structurelles, mais cette fois-ci, avant d'envisager la modif, tu vas rechercher dans l'intégralité du code les occurrences de ce que je vais énoncer. Actuellement, on a défini « fast key » comme zéro étant la nouvelle lune, ce qui est très déstabilisant pour un humain, puisque d'ordinaire, on fait en fait huit phases et donc il vaut mieux commencer à la nouvelle lune à 1. Donc 1 serait nouvelle lune et 8 serait la phase juste avant la nouvelle lune. Est-ce que tu penses qu'on peut modifier dans l'intégralité du code et dans la logique ce truc-là et comment on peut patcher éventuellement les données qui sont déjà en base de données et qui sont enregistrées comme ça ? J'ai un peu peur parce que des fois, il y a des JSON, des trucs comme ça. Donc en fait, est-ce qu'il faut que je recommence toute la base ou est-ce qu'on peut arranger les choses maintenant ou pas ?