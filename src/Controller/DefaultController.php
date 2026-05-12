<?php

namespace App\Controller;

use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
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

    public function sidebar(
        Request $request,
        ProductRepository $productRepository,
        LocationRepository $locationRepository,
    ): Response {
        $currentRoute = $request->attributes->get('_route');

        $productCounts = $productRepository->countTypesAndTotal();
        $locationCounts = $locationRepository->count(['isActive' => true]);

        $alerts = [
            'products' => $productCounts['total'] ?? 0,
            'finishedProducts' => $productCounts['finishedCount'] ?? 0,
            'semiProducts' => $productCounts['semiCount'] ?? 0,
            'rawProducts' => $productCounts['rawCount'] ?? 0,
            'consumableProducts' => $productCounts['consumablesCount'] ?? 0,
            'locations' => $locationCounts ?? 0,
        ];

        $queryString = $request->getQueryString();
        $currentUrl = $request->getPathInfo().($queryString ? '?'.$queryString : '');

        return $this->render('layout/_partials/sidebar.html.twig', [
            'currentUrl' => $currentUrl,
            'alerts' => $alerts,
        ]);
    }
}
