<?php

namespace App\Tests\Unit\Service;

use App\Entity\Adjustment;
use App\Entity\Stock;
use App\Enum\StocktakingStatus;
use App\Repository\StockRepository;
use App\Service\OperationService;
use App\Service\StocktakingService;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\StocktakingFactory;
use App\Tests\Factory\StocktakingLineFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class StocktakingServiceTest extends TestCase
{
    private MockObject&StockRepository $stockRepository;
    private MockObject&OperationService $operationService;
    private MockObject&EntityManagerInterface $entityManager;
    private StocktakingService $service;

    protected function setUp(): void
    {
        $this->stockRepository = $this->createMock(StockRepository::class);
        $this->operationService = $this->createMock(OperationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new StocktakingService(
            $this->stockRepository,
            $this->operationService,
            $this->entityManager,
        );
    }

    private function mockGenerateNumberCapture(): \stdClass
    {
        $captured = new \stdClass();
        $captured->adjustment = null;

        $this->operationService->method('generateNumber')->willReturnCallback(function ($adjustment) use ($captured) {
            $captured->adjustment = $adjustment;

            return $adjustment;
        });

        return $captured;
    }

    public function testCreateBuildsStocktakingLinesFromAllStockAndPersists(): void
    {
        $productA = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $stockA = (new Stock())->setProduct($productA)->setLocation($locationA)->setQuantity('5.000');

        $productB = ProductFactory::createOne();
        $locationB = LocationFactory::createOne();
        $stockB = (new Stock())->setProduct($productB)->setLocation($locationB)->setQuantity('3.000');

        $this->stockRepository->expects($this->once())
            ->method('findAllWithRelations')
            ->willReturn([$stockA, $stockB]);

        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);

        $this->entityManager->expects($this->once())->method('persist')->with($stocktaking);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->create($stocktaking);

        self::assertSame(StocktakingStatus::OPEN, $stocktaking->getStatus());
        self::assertCount(2, $stocktaking->getStocktakingLines());

        $lines = $stocktaking->getStocktakingLines()->toArray();
        self::assertSame($productA, $lines[0]->getProduct());
        self::assertSame($locationA, $lines[0]->getLocation());
        self::assertSame('5.000', $lines[0]->getExpectedQuantity());
        self::assertSame($productB, $lines[1]->getProduct());
        self::assertSame($locationB, $lines[1]->getLocation());
        self::assertSame('3.000', $lines[1]->getExpectedQuantity());
    }

    public function testCreateWithNoStockStillPersistsEmptyStocktaking(): void
    {
        $this->stockRepository->expects($this->once())->method('findAllWithRelations')->willReturn([]);

        $stocktaking = StocktakingFactory::createOne();

        $this->entityManager->expects($this->once())->method('persist')->with($stocktaking);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->create($stocktaking);

        self::assertCount(0, $stocktaking->getStocktakingLines());
        self::assertSame(StocktakingStatus::OPEN, $stocktaking->getStatus());
    }

    public function testSaveLineThrowsWhenStocktakingIsCompleted(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::COMPLETED]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można modyfikować linii zakończonej lub anulowanej inwentaryzacji.');

        $this->service->saveLine($line, '5.000', UserFactory::createOne());
    }

    public function testSaveLineThrowsWhenStocktakingIsCancelled(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::CANCELLED]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można modyfikować linii zakończonej lub anulowanej inwentaryzacji.');

        $this->service->saveLine($line, '5.000', UserFactory::createOne());
    }

    public function testSaveLineThrowsWhenCountedQuantityIsNotNumeric(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Podana ilość jest nieprawidłowa.');

        $this->service->saveLine($line, 'abc', UserFactory::createOne());
    }

    public function testSaveLineThrowsWhenCountedQuantityIsNegative(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Podana ilość jest nieprawidłowa.');

        $this->service->saveLine($line, '-1.000', UserFactory::createOne());
    }

    public function testSaveLineAcceptsZeroAsValidQuantity(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);
        $user = UserFactory::createOne();

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->saveLine($line, '0.000', $user);

        self::assertSame('0.000', $line->getCountedQuantity());
        self::assertInstanceOf(\DateTimeImmutable::class, $line->getSavedAt());
        self::assertSame($user, $line->getSavedBy());
    }

    public function testSaveLineSetsCountedQuantitySavedAtAndSavedByWhenValid(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);
        $user = UserFactory::createOne();

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->saveLine($line, '12.500', $user);

        self::assertSame('12.500', $line->getCountedQuantity());
        self::assertInstanceOf(\DateTimeImmutable::class, $line->getSavedAt());
        self::assertSame($user, $line->getSavedBy());
    }

    public function testSaveLineClearsSavedAtAndSavedByWhenQuantityIsNull(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $line = StocktakingLineFactory::createOne([
            'countedQuantity' => '5.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]);
        $stocktaking->addStocktakingLine($line);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->saveLine($line, null, UserFactory::createOne());

        self::assertNull($line->getCountedQuantity());
        self::assertNull($line->getSavedAt());
        self::assertNull($line->getSavedBy());
    }

    public function testSaveLineTransitionsStocktakingFromOpenToInProgress(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->service->saveLine($line, '1.000', UserFactory::createOne());

        self::assertSame(StocktakingStatus::IN_PROGRESS, $stocktaking->getStatus());
    }

    public function testSaveLineDoesNotChangeStatusWhenAlreadyInProgress(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $line = StocktakingLineFactory::createOne();
        $stocktaking->addStocktakingLine($line);

        $this->service->saveLine($line, '1.000', UserFactory::createOne());

        self::assertSame(StocktakingStatus::IN_PROGRESS, $stocktaking->getStatus());
    }

    public function testCompleteThrowsWhenStocktakingIsCompleted(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::COMPLETED]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->operationService->expects($this->never())->method('generateNumber');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można zatwierdzić zakończonej lub anulowanej inwentaryzacji.');

        $this->service->complete($stocktaking, UserFactory::createOne());
    }

    public function testCompleteThrowsWhenStocktakingIsCancelled(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::CANCELLED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można zatwierdzić zakończonej lub anulowanej inwentaryzacji.');

        $this->service->complete($stocktaking, UserFactory::createOne());
    }

    public function testCompleteSkipsUnsavedLines(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne(['expectedQuantity' => '5.000']));

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, UserFactory::createOne());

        self::assertCount(0, $captured->adjustment->getOperationLines());
    }

    public function testCompleteSkipsLinesWithZeroDifference(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'expectedQuantity' => '5.000',
            'countedQuantity' => '5.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, UserFactory::createOne());

        self::assertCount(0, $captured->adjustment->getOperationLines());
    }

    public function testCompletePositiveDifferenceCreatesLocationToLine(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'product' => $product,
            'location' => $location,
            'expectedQuantity' => '5.000',
            'countedQuantity' => '8.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, UserFactory::createOne());

        $operationLines = $captured->adjustment->getOperationLines();
        self::assertCount(1, $operationLines);
        $opLine = $operationLines->first();
        self::assertSame($product, $opLine->getProduct());
        self::assertSame($location, $opLine->getLocationTo());
        self::assertNull($opLine->getLocationFrom());
        self::assertSame('3.000', $opLine->getQuantity());
    }

    public function testCompleteNegativeDifferenceCreatesLocationFromLineWithAbsoluteQuantity(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'product' => $product,
            'location' => $location,
            'expectedQuantity' => '5.000',
            'countedQuantity' => '2.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, UserFactory::createOne());

        $operationLines = $captured->adjustment->getOperationLines();
        self::assertCount(1, $operationLines);
        $opLine = $operationLines->first();
        self::assertSame($product, $opLine->getProduct());
        self::assertSame($location, $opLine->getLocationFrom());
        self::assertNull($opLine->getLocationTo());
        self::assertSame('3.000', $opLine->getQuantity());
    }

    public function testCompleteBuildsAdjustmentLinkedToStocktakingAndCreatedBy(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'expectedQuantity' => '5.000',
            'countedQuantity' => '8.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));
        $completedBy = UserFactory::createOne();

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, $completedBy);

        self::assertSame($stocktaking, $captured->adjustment->getStocktaking());
        self::assertSame($completedBy, $captured->adjustment->getCreatedBy());
        self::assertInstanceOf(\DateTimeImmutable::class, $captured->adjustment->getDocumentDate());
    }

    public function testCompleteSetsStocktakingStatusCompletedWithTimestampAndUser(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $completedBy = UserFactory::createOne();

        $this->service->complete($stocktaking, $completedBy);

        self::assertSame(StocktakingStatus::COMPLETED, $stocktaking->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $stocktaking->getCompletedAt());
        self::assertSame($completedBy, $stocktaking->getCompletedBy());
    }

    public function testCompletePersistsGeneratesNumberAndConfirmsInOrder(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $completedBy = UserFactory::createOne();

        $calls = [];
        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(Adjustment::class))
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'persist';
            });
        $this->operationService->expects($this->once())->method('generateNumber')
            ->willReturnCallback(function ($adjustment) use (&$calls) {
                $calls[] = 'generateNumber';

                return $adjustment;
            });
        $this->operationService->expects($this->once())->method('confirm')
            ->with($this->isInstanceOf(Adjustment::class), $completedBy)
            ->willReturnCallback(function ($adjustment) use (&$calls) {
                $calls[] = 'confirm';

                return $adjustment;
            });

        $this->service->complete($stocktaking, $completedBy);

        self::assertSame(['persist', 'generateNumber', 'confirm'], $calls);
    }

    public function testCompleteHandlesMultipleLinesIndependently(): void
    {
        $productA = ProductFactory::createOne();
        $productB = ProductFactory::createOne();
        $productC = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();
        $locationC = LocationFactory::createOne();

        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);

        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'product' => $productA,
            'location' => $locationA,
            'expectedQuantity' => '2.000',
            'countedQuantity' => '5.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'product' => $productB,
            'location' => $locationB,
            'expectedQuantity' => '10.000',
            'countedQuantity' => '4.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'product' => $productC,
            'location' => $locationC,
            'expectedQuantity' => '7.000',
            'countedQuantity' => '7.000',
            'savedAt' => new \DateTimeImmutable(),
            'savedBy' => UserFactory::createOne(),
        ]));

        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne([
            'expectedQuantity' => '1.000',
        ]));

        $captured = $this->mockGenerateNumberCapture();

        $this->service->complete($stocktaking, UserFactory::createOne());

        $operationLines = $captured->adjustment->getOperationLines()->toArray();
        self::assertCount(2, $operationLines);

        self::assertSame($productA, $operationLines[0]->getProduct());
        self::assertSame($locationA, $operationLines[0]->getLocationTo());
        self::assertNull($operationLines[0]->getLocationFrom());
        self::assertSame('3.000', $operationLines[0]->getQuantity());

        self::assertSame($productB, $operationLines[1]->getProduct());
        self::assertSame($locationB, $operationLines[1]->getLocationFrom());
        self::assertNull($operationLines[1]->getLocationTo());
        self::assertSame('6.000', $operationLines[1]->getQuantity());
    }

    public function testCompleteWithNoQualifyingLinesStillConfirmsEmptyAdjustment(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::IN_PROGRESS]);
        $stocktaking->addStocktakingLine(StocktakingLineFactory::createOne(['expectedQuantity' => '5.000']));

        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Adjustment::class));
        $this->operationService->expects($this->once())->method('generateNumber')->willReturnArgument(0);
        $this->operationService->expects($this->once())->method('confirm')->willReturnArgument(0);

        $this->service->complete($stocktaking, UserFactory::createOne());
    }

    public function testCancelThrowsWhenStocktakingIsCompleted(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::COMPLETED]);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można anulować zakończonej lub anulowanej inwentaryzacji.');

        $this->service->cancel($stocktaking, UserFactory::createOne());
    }

    public function testCancelThrowsWhenStocktakingIsCancelled(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::CANCELLED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można anulować zakończonej lub anulowanej inwentaryzacji.');

        $this->service->cancel($stocktaking, UserFactory::createOne());
    }

    public function testCancelSetsStatusCancelledWithTimestampAndUser(): void
    {
        $stocktaking = StocktakingFactory::createOne(['status' => StocktakingStatus::OPEN]);
        $cancelledBy = UserFactory::createOne();

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->cancel($stocktaking, $cancelledBy);

        self::assertSame(StocktakingStatus::CANCELLED, $stocktaking->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $stocktaking->getCompletedAt());
        self::assertSame($cancelledBy, $stocktaking->getCompletedBy());
    }
}
