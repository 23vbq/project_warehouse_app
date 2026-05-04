<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_default')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    public function sidebar(Request $request): Response
    {
        $currentRoute = $request->attributes->get('_route');

        $alerts = [
            'products' => 1348,
            'semiproducts' => 256,
        ];

        return $this->render('layout/_partials/sidebar.html.twig', [
            'currentRoute' => $currentRoute,
            'currentParams' => $request->query->all(),
            'alerts' => $alerts,
        ]);
    }
}
