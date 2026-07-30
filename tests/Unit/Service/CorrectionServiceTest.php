<?php

namespace App\Tests\Unit\Service;

use App\Entity\Location;
use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Product;
use App\Enum\OperationStatus;
use App\Service\CorrectionService;
use App\Service\StockService;
use App\Tests\Factory\CorrectionFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\OperationLineFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ReceiptFactory;
use App\Tests\Factory\RelocationFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function Zenstruck\Foundry\force;

#[AllowMockObjectsWithoutExpectations]
final class CorrectionServiceTest extends TestCase
{
    private MockObject&StockService $stockService;
    private CorrectionService $service;

    protected function setUp(): void
    {
        $this->stockService = $this->createMock(StockService::class);
        $this->service = new CorrectionService($this->stockService);
    }

    private function line(Product $product, mixed $quantity, ?Location $from = null, ?Location $to = null): OperationLine
    {
        return OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => $quantity,
            'locationFrom' => $from,
            'locationTo' => $to,
        ]);
    }

    public function testBuildLinesReplacesExistingLinesWithComputedOnes(): void
    {
        $correction = CorrectionFactory::createOne();
        $oldLine1 = $this->line(ProductFactory::createOne(), '1.000', to: LocationFactory::createOne());
        $oldLine2 = $this->line(ProductFactory::createOne(), '2.000', to: LocationFactory::createOne());
        $correction->addOperationLine($oldLine1);
        $correction->addOperationLine($oldLine2);

        $newLine = $this->line(ProductFactory::createOne(), '3.000', to: LocationFactory::createOne());

        $this->service->buildLines($correction, [$newLine]);

        self::assertCount(1, $correction->getOperationLines());
        self::assertSame($newLine, $correction->getOperationLines()->first());
        self::assertFalse($correction->getOperationLines()->contains($oldLine1));
        self::assertFalse($correction->getOperationLines()->contains($oldLine2));
    }

    public function testBuildLinesWithEmptyComputedRemovesAllLines(): void
    {
        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line(ProductFactory::createOne(), '1.000', to: LocationFactory::createOne()));

        $this->service->buildLines($correction, []);

        self::assertCount(0, $correction->getOperationLines());
    }

    public function testComputeLinesReceiptRemovedLineProducesSingleReversalLine(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($product, '10.000', to: $locationX);

        $result = $this->service->computeLines([], [$original], Operation::TYPE_RECEIPT);

        self::assertCount(1, $result);
        self::assertSame($product, $result[0]->getProduct());
        self::assertSame('10.000', $result[0]->getQuantity());
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
    }

    public function testComputeLinesReleaseRemovedLineProducesSingleReversalLine(): void
    {
        $product = ProductFactory::createOne();
        $locationY = LocationFactory::createOne();
        $original = $this->line($product, '5.000', from: $locationY);

        $result = $this->service->computeLines([], [$original], Operation::TYPE_RELEASE);

        self::assertCount(1, $result);
        self::assertSame('5.000', $result[0]->getQuantity());
        self::assertNull($result[0]->getLocationFrom());
        self::assertSame($locationY, $result[0]->getLocationTo());
    }

    public function testComputeLinesRelocationRemovedLineProducesTwoReversalLines(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();
        $original = $this->line($product, '7.000', from: $locationA, to: $locationB);

        $result = $this->service->computeLines([], [$original], Operation::TYPE_RELOCATION);

        self::assertCount(2, $result);
        self::assertSame($locationB, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('7.000', $result[0]->getQuantity());
        self::assertNull($result[1]->getLocationFrom());
        self::assertSame($locationA, $result[1]->getLocationTo());
        self::assertSame('7.000', $result[1]->getQuantity());
    }

    public function testComputeLinesAdjustmentRemovedLineProducesSingleReversalLineWithFlippedDirection(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($product, '3.000', to: $locationX);

        $result = $this->service->computeLines([], [$original], Operation::TYPE_ADJUSTMENT);

        self::assertCount(1, $result);
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('3.000', $result[0]->getQuantity());
    }

    public function testComputeLinesNoChangeWhenQuantityAndLocationMatchExpectedShape(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($product, '10.000', to: $locationX);
        $desired = $this->line($product, '10.000', from: $locationX);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RECEIPT);

        self::assertSame([], $result);
    }

    public function testComputeLinesQuantityDecreaseProducesDeltaLineInReversalDirection(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($product, '10.000', to: $locationX);
        $desired = $this->line($product, '6.000', from: $locationX);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RECEIPT);

        self::assertCount(1, $result);
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('4.000', $result[0]->getQuantity());
    }

    public function testComputeLinesQuantityIncreaseProducesDeltaLineInOppositeDirection(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($product, '10.000', to: $locationX);
        $desired = $this->line($product, '14.000', from: $locationX);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RECEIPT);

        self::assertCount(1, $result);
        self::assertNull($result[0]->getLocationFrom());
        self::assertSame($locationX, $result[0]->getLocationTo());
        self::assertSame('4.000', $result[0]->getQuantity());
    }

    public function testComputeLinesRelocationQuantityDecreaseProducesDeltaSplitWithoutLocationChange(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();
        $original = $this->line($product, '10.000', from: $locationA, to: $locationB);
        $desired = $this->line($product, '6.000', from: $locationB, to: $locationA);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RELOCATION);

        self::assertCount(2, $result);
        self::assertSame($locationB, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('4.000', $result[0]->getQuantity());
        self::assertNull($result[1]->getLocationFrom());
        self::assertSame($locationA, $result[1]->getLocationTo());
        self::assertSame('4.000', $result[1]->getQuantity());
    }

    public function testComputeLinesProductChangeProducesReversalAndApplicationLines(): void
    {
        $productP = ProductFactory::createOne();
        $productQ = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $original = $this->line($productP, '10.000', to: $locationX);
        $desired = $this->line($productQ, '8.000', from: $locationX);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RECEIPT);

        self::assertCount(2, $result);
        self::assertSame($productP, $result[0]->getProduct());
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('10.000', $result[0]->getQuantity());
        self::assertSame($productQ, $result[1]->getProduct());
        self::assertNull($result[1]->getLocationFrom());
        self::assertSame($locationX, $result[1]->getLocationTo());
        self::assertSame('8.000', $result[1]->getQuantity());
    }

    public function testComputeLinesLocationChangeProducesReversalAndApplicationLines(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $locationY = LocationFactory::createOne();
        $original = $this->line($product, '10.000', to: $locationX);
        $desired = $this->line($product, '10.000', from: $locationY);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RECEIPT);

        self::assertCount(2, $result);
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertSame('10.000', $result[0]->getQuantity());
        self::assertSame($locationY, $result[1]->getLocationTo());
        self::assertSame('10.000', $result[1]->getQuantity());
    }

    public function testComputeLinesRelocationDestinationChangeOnlyProducesDesiredSplitWithoutReversal(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();
        $locationC = LocationFactory::createOne();
        $original = $this->line($product, '5.000', from: $locationA, to: $locationB);
        $desired = $this->line($product, '5.000', from: $locationB, to: $locationC);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RELOCATION);

        self::assertCount(2, $result);
        self::assertSame($locationB, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertSame('5.000', $result[0]->getQuantity());
        self::assertNull($result[1]->getLocationFrom());
        self::assertSame($locationC, $result[1]->getLocationTo());
        self::assertSame('5.000', $result[1]->getQuantity());
    }

    public function testComputeLinesRelocationSourceChangeProducesReversalPlusDesiredSplit(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();
        $locationD = LocationFactory::createOne();
        $original = $this->line($product, '5.000', from: $locationA, to: $locationB);
        $desired = $this->line($product, '5.000', from: $locationD, to: $locationA);

        $result = $this->service->computeLines([$desired], [$original], Operation::TYPE_RELOCATION);

        self::assertCount(4, $result);
        self::assertSame($locationB, $result[0]->getLocationFrom());
        self::assertNull($result[0]->getLocationTo());
        self::assertNull($result[1]->getLocationFrom());
        self::assertSame($locationA, $result[1]->getLocationTo());
        self::assertSame($locationD, $result[2]->getLocationFrom());
        self::assertNull($result[2]->getLocationTo());
        self::assertNull($result[3]->getLocationFrom());
        self::assertSame($locationA, $result[3]->getLocationTo());
    }

    public function testComputeLinesProcessesMultipleBaseLinesIndependently(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $locationY = LocationFactory::createOne();

        $removedLine = $this->line($product, '2.000', to: $locationX);
        $unchangedLine = $this->line($product, '3.000', to: $locationY);
        $deltaLine = $this->line($product, '10.000', to: $locationX);

        $desiredForUnchanged = $this->line($product, '3.000', from: $locationY);
        $desiredForDelta = $this->line($product, '6.000', from: $locationX);

        $result = $this->service->computeLines(
            [1 => $desiredForUnchanged, 2 => $desiredForDelta],
            [$removedLine, $unchangedLine, $deltaLine],
            Operation::TYPE_RECEIPT,
        );

        self::assertCount(2, $result);
        self::assertSame('2.000', $result[0]->getQuantity());
        self::assertSame($locationX, $result[0]->getLocationFrom());
        self::assertSame('4.000', $result[1]->getQuantity());
        self::assertSame($locationX, $result[1]->getLocationFrom());
    }

    public function testComputeEffectiveLinesReturnsEmptyWithNoCorrectionsAtAll(): void
    {
        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line(ProductFactory::createOne(), '5.000', to: LocationFactory::createOne()));

        self::assertSame([], $this->service->computeEffectiveLines($receipt, []));
    }

    public function testComputeEffectiveLinesReturnsEmptyWhenOnlyDraftCorrectionsExist(): void
    {
        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line(ProductFactory::createOne(), '5.000', to: LocationFactory::createOne()));

        $draftCorrection = CorrectionFactory::createOne();

        self::assertSame([], $this->service->computeEffectiveLines($receipt, [$draftCorrection]));
    }

    public function testComputeEffectiveLinesAccumulatesOriginalAndConfirmedCorrectionLines(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();

        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line($product, '10.000', to: $locationX));

        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($product, '3.000', from: $locationX));

        $result = $this->service->computeEffectiveLines($receipt, [$correction]);

        self::assertCount(1, $result);
        self::assertSame($product, $result[0]->getProduct());
        self::assertSame($locationX, $result[0]->getLocationTo());
        self::assertSame('7.000', $result[0]->getQuantity());
    }

    public function testComputeEffectiveLinesOmitsEntriesThatNetToZero(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();

        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line($product, '10.000', to: $locationX));

        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($product, '10.000', from: $locationX));

        self::assertSame([], $this->service->computeEffectiveLines($receipt, [$correction]));
    }

    public function testComputeEffectiveLinesAccumulatesAcrossMultipleConfirmedCorrections(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();

        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line($product, '10.000', to: $locationX));

        $correction1 = CorrectionFactory::createOne();
        $correction1->setStatus(OperationStatus::CONFIRMED);
        $correction1->addOperationLine($this->line($product, '2.000', from: $locationX));

        $correction2 = CorrectionFactory::createOne();
        $correction2->setStatus(OperationStatus::CONFIRMED);
        $correction2->addOperationLine($this->line($product, '3.000', from: $locationX));

        $result = $this->service->computeEffectiveLines($receipt, [$correction1, $correction2]);

        self::assertCount(1, $result);
        self::assertSame('5.000', $result[0]->getQuantity());
        self::assertSame($locationX, $result[0]->getLocationTo());
    }

    public function testComputeEffectiveLinesIgnoresUnconfirmedCorrectionsWhenMixedWithConfirmedOnes(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();

        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine($this->line($product, '10.000', to: $locationX));

        $confirmedCorrection = CorrectionFactory::createOne();
        $confirmedCorrection->setStatus(OperationStatus::CONFIRMED);
        $confirmedCorrection->addOperationLine($this->line($product, '2.000', from: $locationX));

        $draftCorrection = CorrectionFactory::createOne();
        $draftCorrection->addOperationLine($this->line($product, '100.000', from: $locationX));

        $result = $this->service->computeEffectiveLines($receipt, [$confirmedCorrection, $draftCorrection]);

        self::assertCount(1, $result);
        self::assertSame('8.000', $result[0]->getQuantity());
    }

    public function testComputeEffectiveLinesPairsEqualRelocationQuantities(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();

        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine($this->line($product, '5.000', from: $locationA, to: $locationB));

        $otherProduct = ProductFactory::createOne();
        $locationZ = LocationFactory::createOne();
        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($otherProduct, '1.000', to: $locationZ));
        $correction->addOperationLine($this->line($otherProduct, '1.000', from: $locationZ));

        $result = $this->service->computeEffectiveLines($relocation, [$correction]);

        self::assertCount(1, $result);
        self::assertSame($product, $result[0]->getProduct());
        self::assertSame($locationA, $result[0]->getLocationFrom());
        self::assertSame($locationB, $result[0]->getLocationTo());
        self::assertSame('5.000', $result[0]->getQuantity());
    }

    public function testComputeEffectiveLinesPairsUnequalRelocationQuantitiesWithLeftoverRemainder(): void
    {
        $product = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();

        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine($this->line($product, '10.000', from: $locationA, to: $locationB));

        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($product, '4.000', from: $locationB));

        $result = $this->service->computeEffectiveLines($relocation, [$correction]);

        self::assertCount(2, $result);
        self::assertSame($locationA, $result[0]->getLocationFrom());
        self::assertSame($locationB, $result[0]->getLocationTo());
        self::assertSame('6.000', $result[0]->getQuantity());
        self::assertSame($locationA, $result[1]->getLocationFrom());
        self::assertNull($result[1]->getLocationTo());
        self::assertSame('4.000', $result[1]->getQuantity());
    }

    public function testComputeEffectiveLinesKeepsFullyUnpairedEntryAsXorLine(): void
    {
        $product = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();

        $relocation = RelocationFactory::createOne();

        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($product, '5.000', to: $locationX));

        $result = $this->service->computeEffectiveLines($relocation, [$correction]);

        self::assertCount(1, $result);
        self::assertNull($result[0]->getLocationFrom());
        self::assertSame($locationX, $result[0]->getLocationTo());
        self::assertSame('5.000', $result[0]->getQuantity());
    }

    public function testComputeEffectiveLinesPairsIndependentlyPerProduct(): void
    {
        $productP = ProductFactory::createOne();
        $productQ = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();

        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine($this->line($productP, '5.000', from: $locationA, to: $locationB));
        $relocation->addOperationLine($this->line($productQ, '8.000', from: $locationB, to: $locationA));

        $noopProduct = ProductFactory::createOne();
        $noopLocation = LocationFactory::createOne();
        $correction = CorrectionFactory::createOne();
        $correction->setStatus(OperationStatus::CONFIRMED);
        $correction->addOperationLine($this->line($noopProduct, '1.000', to: $noopLocation));
        $correction->addOperationLine($this->line($noopProduct, '1.000', from: $noopLocation));

        $result = $this->service->computeEffectiveLines($relocation, [$correction]);

        self::assertCount(2, $result);

        $byProductId = [];
        foreach ($result as $line) {
            $byProductId[$line->getProduct()->getId()] = $line;
        }

        self::assertSame($locationA, $byProductId[$productP->getId()]->getLocationFrom());
        self::assertSame($locationB, $byProductId[$productP->getId()]->getLocationTo());
        self::assertSame('5.000', $byProductId[$productP->getId()]->getQuantity());
        self::assertSame($locationB, $byProductId[$productQ->getId()]->getLocationFrom());
        self::assertSame($locationA, $byProductId[$productQ->getId()]->getLocationTo());
        self::assertSame('8.000', $byProductId[$productQ->getId()]->getQuantity());
    }

    public function testConfirmDispatchesAddOrSubtractPerLineDirection(): void
    {
        $productA = ProductFactory::createOne();
        $productB = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $locationY = LocationFactory::createOne();

        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line($productA, '5.000', to: $locationX));
        $correction->addOperationLine($this->line($productB, '2.000', from: $locationY));

        $calls = [];
        $this->stockService->expects($this->once())->method('add')
            ->with($productA, $locationX, '5.000')
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'add';
            });
        $this->stockService->expects($this->once())->method('subtract')
            ->with($productB, $locationY, '2.000')
            ->willReturnCallback(function () use (&$calls): void {
                $calls[] = 'subtract';
            });

        $this->service->confirm($correction);

        self::assertSame(['add', 'subtract'], $calls);
    }

    public function testValidateForConfirmationThrowsWhenNoLines(): void
    {
        $correction = CorrectionFactory::createOne();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Korekta musi zawierać co najmniej jedną pozycję.');

        $this->service->validateForConfirmation($correction);
    }

    public function testValidateForConfirmationThrowsWhenLineQuantityMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line($product, force(null), to: LocationFactory::createOne()));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ilość jest wymagana dla pozycji "Śruba M6".');

        $this->service->validateForConfirmation($correction);
    }

    public function testValidateForConfirmationThrowsWhenLocationsBothMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line($product, '1.000'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pozycja "Śruba M6" nie ma ustawionej żadnej lokalizacji — wymagana jest lokalizacja źródłowa lub docelowa.');

        $this->service->validateForConfirmation($correction);
    }

    public function testValidateForConfirmationThrowsWhenBothLocationsSet(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line(
            $product,
            '1.000',
            from: LocationFactory::createOne(),
            to: LocationFactory::createOne(),
        ));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pozycja "Śruba M6" ma ustawione obie lokalizacje jednocześnie — dozwolona jest tylko jedna (źródłowa lub docelowa).');

        $this->service->validateForConfirmation($correction);
    }

    public function testValidateForConfirmationPassesForValidCorrection(): void
    {
        $correction = CorrectionFactory::createOne();
        $correction->addOperationLine($this->line(ProductFactory::createOne(), '1.000', to: LocationFactory::createOne()));
        $correction->addOperationLine($this->line(ProductFactory::createOne(), '2.000', from: LocationFactory::createOne()));

        $this->expectNotToPerformAssertions();

        $this->service->validateForConfirmation($correction);
    }
}
