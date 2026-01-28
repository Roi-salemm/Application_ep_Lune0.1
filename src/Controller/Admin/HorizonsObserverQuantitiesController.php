<?php

namespace App\Controller\Admin;

use App\Service\Moon\Horizons\HorizonsObserverQuantitiesCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HorizonsObserverQuantitiesController extends AbstractController
{
    #[Route('/admin/horizons_observer_quantities', name: 'admin_horizons_observer_quantities', methods: ['GET'])]
    public function index(HorizonsObserverQuantitiesCatalog $catalog): Response
    {
        return $this->render('admin/horizons_observer_quantities.html.twig', [
            'quantities' => $catalog->all(),
            'macros' => $catalog->macros(),
        ]);
    }
}
