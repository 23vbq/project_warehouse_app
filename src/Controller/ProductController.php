<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\OperationLineRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Traits\TurboTrait;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/products')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    use TurboTrait;

    #[Route('', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $repository): Response
    {
        $filters = [
            'type' => $request->query->get('type'),
            'query' => $request->query->get('query'),
            'showInactive' => $request->query->getBoolean('showInactive'),
        ];
        $orderBy = [
            'field' => $request->query->get('sort', 'name'),
            'direction' => $request->query->get('direction', 'asc'),
        ];

        $qb = $repository->createIndexQueryBuilder($filters, [$orderBy['field'] => $orderBy['direction']]);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        $view = 'product/index.html.twig';
        $turboFrameId = $request->headers->get('Turbo-Frame');
        if ('product_list' === $turboFrameId) {
            $view = 'product/list.html.twig';
        } elseif ('product_list_table' === $turboFrameId) {
            $view = 'product/_list_table.html.twig';
        }

        return $this->render($view, [
            'pager' => $pager,
            'filters' => $filters,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/{id}/stock', name: 'app_product_stock_detail')]
    public function stockDetail(
        Product $product,
        StockRepository $stockRepository,
    ): Response {
        $stocks = $stockRepository->findByProduct($product->getId());

        return $this->render('product/stock_detail.html.twig', [
            'product' => $product,
            'stocks' => $stocks,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', requirements: ['id' => '\d+'])]
    public function show(
        Product $product,
        StockRepository $stockRepository,
        ProductRepository $productRepository,
        OperationLineRepository $operationLineRepository,
        ChartBuilderInterface $chartBuilder,
    ): Response {
        $stocks = $stockRepository->findByProduct($product->getId());
        $kpi = $productRepository->getKpiForProduct($product);

        $from = new \DateTimeImmutable('-30 days');
        $dailyNet = $operationLineRepository->findDailyNetByProduct($product, $from);
        $periodStats = $operationLineRepository->findPeriodStatsByProduct($product, $from);

        $chart = $this->buildStockTrendChart($chartBuilder, $kpi['totalStock'] ?? '0', $dailyNet, $from);

        $totalReleased = (float) ($periodStats['totalReleased'] ?? 0);
        $dailyAvg = $totalReleased > 0 ? $totalReleased / 30 : 0;
        $currentStock = (float) ($kpi['totalStock'] ?? 0);

        $trendStats = [
            'totalReceived' => (float) ($periodStats['totalReceived'] ?? 0),
            'totalReleased' => $totalReleased,
            'receiptsCount' => (int) ($periodStats['receiptsCount'] ?? 0),
            'releasesCount' => (int) ($periodStats['releasesCount'] ?? 0),
            'dailyAvg' => $dailyAvg,
            'daysOfStock' => $dailyAvg > 0 ? (int) round($currentStock / $dailyAvg) : null,
        ];

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'stocks' => $stocks,
            'kpi' => $kpi,
            'chart' => $chart,
            'trendStats' => $trendStats,
        ]);
    }

    private function buildStockTrendChart(
        ChartBuilderInterface $chartBuilder,
        string $currentStock,
        array $dailyNet,
        \DateTimeImmutable $from,
    ): Chart {
        // index movements by day
        $netByDay = [];
        foreach ($dailyNet as $row) {
            $netByDay[$row['day']] = (float) $row['netChange'];
        }

        // build 31-point series: index 0 = 30 days ago, index 30 = today
        $labels = [];
        $data = [];
        $stock = (float) $currentStock;

        for ($i = 30; $i >= 0; --$i) {
            $daysAgo = 30 - $i;
            $day = (new \DateTimeImmutable("$daysAgo days ago"))->format('Y-m-d');
            $labels[$i] = (new \DateTimeImmutable("$daysAgo days ago"))->format('d.m');
            $data[$i] = $stock;
            $stock -= $netByDay[$day] ?? 0;
        }

        ksort($labels);
        ksort($data);

        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => array_values($labels),
            'datasets' => [[
                'data' => array_values($data),
                'borderColor' => 'oklch(0.65 0.19 295)',
                'backgroundColor' => 'oklch(0.65 0.19 295 / 0.08)',
                'fill' => true,
                'tension' => 0.3,
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
                'borderWidth' => 1.5,
            ]],
        ]);
        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxTicksLimit' => 6, 'color' => '#5e5e67', 'font' => ['size' => 10, 'family' => "'JetBrains Mono'"]],
                    'border' => ['display' => false],
                ],
                'y' => [
                    'grid' => ['color' => '#26262c'],
                    'ticks' => ['color' => '#5e5e67', 'font' => ['size' => 10, 'family' => "'JetBrains Mono'"]],
                    'border' => ['display' => false],
                ],
            ],
        ]);

        return $chart;
    }

    #[Route('/{id}/movements', name: 'app_product_movements', requirements: ['id' => '\d+'])]
    public function movements(
        Request $request,
        Product $product,
        OperationLineRepository $operationLineRepository,
    ): Response {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        $qb = $operationLineRepository->createByProductQueryBuilder($product);

        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(15);
        $pager->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        return $this->render('product/_partials/show_movements.html.twig', [
            'product' => $product,
            'pager' => $pager,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        $product = new Product();
        $product->setCreatedBy($this->getUser());

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Produkt "%s" został dodany.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        if (!$product->isActive()) {
            $this->addFlash('error', sprintf('Produkt "%s" jest nieaktywny i nie może być edytowany.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        $returnTo = $request->query->get('return', 'index');

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Zmiany w produkcie "%s" zostały zapisane.', $product->getName()));

            if ('show' === $returnTo) {
                return $this->turboRedirectToRoute($request, 'app_product_show', ['id' => $product->getId()]);
            }

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'form' => $form,
            'product' => $product,
            'return' => $returnTo,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_product_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WAREHOUSE_MANAGER')]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $em,
    ): Response {
        if (!$request->headers->has('Turbo-Frame')) {
            return $this->redirectToRoute('app_product_index');
        }

        if (!$product->isActive()) {
            $this->addFlash('error', sprintf('Produkt "%s" jest już nieaktywny.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $product->setIsActive(false);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', sprintf('Produkt "%s" został dezaktywowany.', $product->getName()));

            return $this->turboRedirectToRoute($request, 'app_product_index');
        }

        return $this->render('product/delete.html.twig', [
            'product' => $product,
        ]);
    }
}
