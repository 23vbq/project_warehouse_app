<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    private const VALID_PERIODS = ['24h', '7d', '30d', 'quarter'];

    #[Route('/', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        ProductRepository $productRepository
    ): Response
    {
        $period = $request->query->get('period', '7d');
        if (!\in_array($period, self::VALID_PERIODS, true)) {
            $period = '7d';
        }

        $filters = [
            'period' => $period,
        ];

        $kpi = $productRepository->getGlobalKpi();

        $view = 'dashboard/index.html.twig';
        if ('dashboard_content' === $request->headers->get('Turbo-Frame')) {
            $view = 'dashboard/content.html.twig';
        }

        return $this->render($view, [
            'filters' => $filters,
            'kpi' => $kpi,
        ]);
    }
}
