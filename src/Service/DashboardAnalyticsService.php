<?php

namespace App\Service;

use App\Repository\OperationLineRepository;
use App\Repository\ProductRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OperationLineRepository $operationLineRepository,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

    public function resolveDateFrom(string $period): \DateTimeImmutable
    {
        return match ($period) {
            '24h'     => new \DateTimeImmutable('-1 day'),
            '7d'      => new \DateTimeImmutable('-7 days'),
            '30d'     => new \DateTimeImmutable('-30 days'),
            'quarter' => new \DateTimeImmutable('-3 months'),
            default   => throw new \InvalidArgumentException('Invalid period: ' . $period),
        };
    }

    public function getGlobalKpi(): array
    {
        return $this->productRepository->getGlobalKpi();
    }

    public function getMovementsChart(\DateTimeImmutable $from): Chart
    {
        $dailyActivity = $this->operationLineRepository->findDailyActivityForPeriod($from);

        return $this->buildMovementsChart($dailyActivity, $from);
    }

    public function getMovementStats(\DateTimeImmutable $from): array
    {
        $dailyActivity = $this->operationLineRepository->findDailyActivityForPeriod($from);

        return $this->computeMovementStats($dailyActivity);
    }

    public function getMovementsData(\DateTimeImmutable $from): array
    {
        $dailyActivity = $this->operationLineRepository->findDailyActivityForPeriod($from);

        return [
            'chart' => $this->buildMovementsChart($dailyActivity, $from),
            'stats' => $this->computeMovementStats($dailyActivity),
        ];
    }

    private function computeMovementStats(array $dailyActivity): array
    {
        $receiptsCount = array_sum(array_column($dailyActivity, 'receiptsCount'));
        $releasesCount = array_sum(array_column($dailyActivity, 'releasesCount'));

        return [
            'receiptsCount' => (int) $receiptsCount,
            'releasesCount' => (int) $releasesCount,
            'netBalance'    => (int) $receiptsCount - (int) $releasesCount,
        ];
    }

    private function rollingAverage(array $values, int $window): array
    {
        $result = [];
        foreach ($values as $i => $value) {
            $slice = array_slice($values, max(0, $i - $window + 1), min($window, $i + 1));
            $result[] = round(array_sum($slice) / count($slice), 1);
        }

        return $result;
    }

    private function buildMovementsChart(array $dailyActivity, \DateTimeImmutable $from): Chart
    {
        $byDay = [];
        foreach ($dailyActivity as $row) {
            $byDay[$row['day']] = [
                'received' => (int) $row['receiptsCount'],
                'released' => (int) $row['releasesCount'],
            ];
        }

        $labels = [];
        $receivedData = [];
        $releasedData = [];

        $today = new \DateTimeImmutable('today');
        $current = new \DateTimeImmutable($from->format('Y-m-d'));

        while ($current <= $today) {
            $day = $current->format('Y-m-d');
            $labels[] = $current->format('d.m');
            $receivedData[] = $byDay[$day]['received'] ?? 0;
            $releasedData[] = $byDay[$day]['released'] ?? 0;
            $current = $current->modify('+1 day');
        }

        $days = count($labels);
        $windowSize = match(true) {
            $days <= 7  => 3,
            $days <= 30 => 7,
            default     => 14,
        };
        $trendData = $this->rollingAverage(
            array_map(fn($r, $l) => $r + $l, $receivedData, $releasedData),
            $windowSize,
        );

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Przyjęcia',
                    'data' => $receivedData,
                    'backgroundColor' => 'oklch(0.72 0.14 155 / 0.7)',
                    'borderRadius' => 2,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Wydania',
                    'data' => $releasedData,
                    'backgroundColor' => 'oklch(0.65 0.19 295 / 0.85)',
                    'borderRadius' => 2,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Trend',
                    'data' => $trendData,
                    'borderColor' => 'oklch(0.78 0.14 75 / 0.9)',
                    'backgroundColor' => 'transparent',
                    'pointRadius' => 0,
                    'pointHoverRadius' => 3,
                    'borderWidth' => 1.5,
                    'tension' => 0.4,
                    'borderDash' => [4, 3],
                ],
            ],
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
                    'ticks' => ['maxTicksLimit' => 8, 'color' => '#5e5e67', 'font' => ['size' => 10, 'family' => "'JetBrains Mono'"]],
                    'border' => ['display' => false],
                ],
                'y' => [
                    'grid' => ['color' => '#26262c'],
                    'ticks' => [
                        'color' => '#5e5e67',
                        'font' => ['size' => 10, 'family' => "'JetBrains Mono'"],
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                    'border' => ['display' => false],
                    'beginAtZero' => true,
                ],
            ],
        ]);

        return $chart;
    }
}
