<?php

namespace App\Tests\Unit\Service;

use App\Repository\LocationRepository;
use App\Repository\OperationLineRepository;
use App\Repository\OperationRepository;
use App\Repository\ProductRepository;
use App\Service\DashboardAnalyticsService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

#[AllowMockObjectsWithoutExpectations]
final class DashboardAnalyticsServiceTest extends TestCase
{
    private MockObject&ProductRepository $productRepository;
    private MockObject&OperationRepository $operationRepository;
    private MockObject&OperationLineRepository $operationLineRepository;
    private MockObject&LocationRepository $locationRepository;
    private DashboardAnalyticsService $service;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->operationLineRepository = $this->createMock(OperationLineRepository::class);
        $this->locationRepository = $this->createMock(LocationRepository::class);

        $this->service = new DashboardAnalyticsService(
            $this->productRepository,
            $this->operationRepository,
            $this->operationLineRepository,
            $this->locationRepository,
            new ChartBuilder(),
        );
    }

    public function testResolveDateFrom24hReturnsOneDayAgo(): void
    {
        $result = $this->service->resolveDateFrom('24h');

        self::assertEqualsWithDelta((new \DateTimeImmutable('-1 day'))->getTimestamp(), $result->getTimestamp(), 5);
    }

    public function testResolveDateFrom7dReturnsSevenDaysAgo(): void
    {
        $result = $this->service->resolveDateFrom('7d');

        self::assertEqualsWithDelta((new \DateTimeImmutable('-7 days'))->getTimestamp(), $result->getTimestamp(), 5);
    }

    public function testResolveDateFrom30dReturnsThirtyDaysAgo(): void
    {
        $result = $this->service->resolveDateFrom('30d');

        self::assertEqualsWithDelta((new \DateTimeImmutable('-30 days'))->getTimestamp(), $result->getTimestamp(), 5);
    }

    public function testResolveDateFromQuarterReturnsThreeMonthsAgo(): void
    {
        $result = $this->service->resolveDateFrom('quarter');

        self::assertEqualsWithDelta((new \DateTimeImmutable('-3 months'))->getTimestamp(), $result->getTimestamp(), 5);
    }

    public function testResolveDateFromThrowsForUnknownPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid period: bogus');

        $this->service->resolveDateFrom('bogus');
    }

    public function testGetGlobalKpiReturnsRepositoryResultUnchanged(): void
    {
        $kpi = ['productCount' => 42, 'totalValue' => '1234.56'];
        $this->productRepository->expects($this->once())->method('getGlobalKpi')->willReturn($kpi);

        self::assertSame($kpi, $this->service->getGlobalKpi());
    }

    public function testGetRecentOperationsUsesDefaultLimitOfEight(): void
    {
        $this->operationRepository->expects($this->once())->method('findRecent')->with(8)->willReturn([]);

        $this->service->getRecentOperations();
    }

    public function testGetRecentOperationsForwardsCustomLimit(): void
    {
        $operations = ['op1', 'op2'];
        $this->operationRepository->expects($this->once())->method('findRecent')->with(3)->willReturn($operations);

        self::assertSame($operations, $this->service->getRecentOperations(3));
    }

    public function testGetLocationHeatmapReturnsEmptyArrayWhenNoRows(): void
    {
        $this->locationRepository->method('getStockTotalsByLocation')->willReturn([]);

        self::assertSame([], $this->service->getLocationHeatmap());
    }

    public function testGetLocationHeatmapAssignsFullOpacityToSingleRowAndPreservesFields(): void
    {
        $this->locationRepository->method('getStockTotalsByLocation')->willReturn([
            ['code' => 'A1', 'name' => 'Regał A1', 'total' => '50.000'],
        ]);

        $result = $this->service->getLocationHeatmap();

        self::assertSame([
            ['code' => 'A1', 'name' => 'Regał A1', 'total' => '50.000', 'opacity' => 1.0],
        ], $result);
    }

    public function testGetLocationHeatmapComputesOpacityRelativeToMax(): void
    {
        $this->locationRepository->method('getStockTotalsByLocation')->willReturn([
            ['code' => 'A1', 'name' => 'Regał A1', 'total' => '25.000'],
            ['code' => 'B1', 'name' => 'Regał B1', 'total' => '100.000'],
            ['code' => 'C1', 'name' => 'Regał C1', 'total' => '0.000'],
        ]);

        $result = $this->service->getLocationHeatmap();

        self::assertSame(0.25, $result[0]['opacity']);
        self::assertSame(1.0, $result[1]['opacity']);
        self::assertSame(0.0, $result[2]['opacity']);
    }

    public function testGetLocationHeatmapAvoidsDivisionByZeroWhenAllTotalsAreZero(): void
    {
        $this->locationRepository->method('getStockTotalsByLocation')->willReturn([
            ['code' => 'A1', 'name' => 'Regał A1', 'total' => '0.000'],
            ['code' => 'B1', 'name' => 'Regał B1', 'total' => '0.000'],
        ]);

        $result = $this->service->getLocationHeatmap();

        self::assertSame(0.0, $result[0]['opacity']);
        self::assertSame(0.0, $result[1]['opacity']);
    }

    public function testGetMovementsChartIsBarType(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([]);

        $chart = $this->service->getMovementsChart(new \DateTimeImmutable('today'));

        self::assertSame(Chart::TYPE_BAR, $chart->getType());
    }

    public function testGetMovementsChartWithFromTodayHasSingleLabel(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([]);

        $from = new \DateTimeImmutable('today');
        $chart = $this->service->getMovementsChart($from);

        self::assertSame([$from->format('d.m')], $chart->getData()['labels']);
    }

    public function testGetMovementsChartFillsMissingDaysWithZero(): void
    {
        $from = (new \DateTimeImmutable('today'))->modify('-2 days');
        $today = $from->modify('+2 days');

        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([
            ['day' => $today->format('Y-m-d'), 'receiptsCount' => '3', 'releasesCount' => '1'],
        ]);

        $chart = $this->service->getMovementsChart($from);
        $data = $chart->getData();

        self::assertSame([0, 0, 3], $data['datasets'][0]['data']);
        self::assertSame([0, 0, 1], $data['datasets'][1]['data']);
    }

    public function testGetMovementsChartDatasetLabelsAndTrendType(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([]);

        $chart = $this->service->getMovementsChart(new \DateTimeImmutable('today'));
        $datasets = $chart->getData()['datasets'];

        self::assertSame('Przyjęcia', $datasets[0]['label']);
        self::assertSame('Wydania', $datasets[1]['label']);
        self::assertSame('Trend', $datasets[2]['label']);
        self::assertSame('line', $datasets[2]['type']);
    }

    public function testGetMovementsChartTrendIsRollingAverageOfCombinedTotals(): void
    {
        $from = (new \DateTimeImmutable('today'))->modify('-2 days');
        $day1 = $from->modify('+1 day');
        $day2 = $from->modify('+2 days');

        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([
            ['day' => $from->format('Y-m-d'), 'receiptsCount' => '2', 'releasesCount' => '0'],
            ['day' => $day1->format('Y-m-d'), 'receiptsCount' => '4', 'releasesCount' => '0'],
            ['day' => $day2->format('Y-m-d'), 'receiptsCount' => '0', 'releasesCount' => '6'],
        ]);

        $chart = $this->service->getMovementsChart($from);
        $trend = $chart->getData()['datasets'][2]['data'];

        // window=3 (<=7 days): [2], [2,4], [2,4,6] -> avg 2.0, 3.0, 4.0
        self::assertSame([2.0, 3.0, 4.0], $trend);
    }

    public static function dayCountProvider(): array
    {
        return [
            'seven days -> window 3' => [7],
            'eight days -> window 7' => [8],
            'thirty days -> window 7' => [30],
            'thirty one days -> window 14' => [31],
        ];
    }

    #[DataProvider('dayCountProvider')]
    public function testGetMovementsChartTrendLengthMatchesLabelsAcrossWindowBoundaries(int $totalDays): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([]);

        $from = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $totalDays - 1));
        $chart = $this->service->getMovementsChart($from);
        $data = $chart->getData();

        self::assertCount($totalDays, $data['labels']);
        self::assertCount($totalDays, $data['datasets'][2]['data']);
    }

    public function testGetMovementStatsSumsReceiptsAndReleasesAcrossDays(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([
            ['day' => '2026-07-01', 'receiptsCount' => '2', 'releasesCount' => '1'],
            ['day' => '2026-07-02', 'receiptsCount' => '5', 'releasesCount' => '3'],
        ]);

        $stats = $this->service->getMovementStats(new \DateTimeImmutable('2026-07-01'));

        self::assertSame(['receiptsCount' => 7, 'releasesCount' => 4, 'netBalance' => 3], $stats);
    }

    public function testGetMovementStatsReturnsZeroesForEmptyActivity(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([]);

        $stats = $this->service->getMovementStats(new \DateTimeImmutable('today'));

        self::assertSame(['receiptsCount' => 0, 'releasesCount' => 0, 'netBalance' => 0], $stats);
    }

    public function testGetMovementStatsNetBalanceIsNegativeWhenReleasesExceedReceipts(): void
    {
        $this->operationLineRepository->method('findDailyActivityForPeriod')->willReturn([
            ['day' => '2026-07-01', 'receiptsCount' => '1', 'releasesCount' => '9'],
        ]);

        $stats = $this->service->getMovementStats(new \DateTimeImmutable('2026-07-01'));

        self::assertSame(-8, $stats['netBalance']);
    }

    public function testGetMovementsDataReturnsChartAndStatsFromSameData(): void
    {
        $from = (new \DateTimeImmutable('today'))->modify('-1 day');
        $this->operationLineRepository->expects($this->once())
            ->method('findDailyActivityForPeriod')
            ->with($from)
            ->willReturn([
                ['day' => $from->format('Y-m-d'), 'receiptsCount' => '2', 'releasesCount' => '1'],
            ]);

        $result = $this->service->getMovementsData($from);

        self::assertInstanceOf(Chart::class, $result['chart']);
        self::assertSame(['receiptsCount' => 2, 'releasesCount' => 1, 'netBalance' => 1], $result['stats']);
    }

    public function testGetMovementsDataFetchesDailyActivityOnlyOnce(): void
    {
        $this->operationLineRepository->expects($this->once())
            ->method('findDailyActivityForPeriod')
            ->willReturn([]);

        $this->service->getMovementsData(new \DateTimeImmutable('today'));
    }
}
