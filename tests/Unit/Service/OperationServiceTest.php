<?php

namespace App\Tests\Unit\Service;

use App\Entity\Operation;
use App\Enum\OperationStatus;
use App\Repository\OperationRepository;
use App\Service\CorrectionService;
use App\Service\OperationService;
use App\Service\StockService;
use App\Tests\Factory\AdjustmentFactory;
use App\Tests\Factory\CorrectionFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\OperationLineFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ReceiptFactory;
use App\Tests\Factory\ReleaseFactory;
use App\Tests\Factory\RelocationFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function Zenstruck\Foundry\force;

#[AllowMockObjectsWithoutExpectations]
final class OperationServiceTest extends TestCase
{
    private MockObject&OperationRepository $operationRepository;
    private MockObject&StockService $stockService;
    private MockObject&CorrectionService $correctionService;
    private OperationService $service;

    protected function setUp(): void
    {
        $this->operationRepository = $this->createMock(OperationRepository::class);
        $this->stockService = $this->createMock(StockService::class);
        $this->correctionService = $this->createMock(CorrectionService::class);

        $this->service = new OperationService(
            $this->operationRepository,
            $this->stockService,
            $this->correctionService,
        );
    }

    public function testGenerateNumberThrowsWhenNumberAlreadySet(): void
    {
        $receipt = ReceiptFactory::createOne(['number' => 5]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Number is already set for this operation.');

        $this->service->generateNumber($receipt);
    }

    public function testGenerateNumberUsesReceiptPrefix(): void
    {
        $receipt = ReceiptFactory::createOne(['documentDate' => new \DateTimeImmutable('2026-07-14')]);

        $this->operationRepository->expects($this->once())
            ->method('getLastNumber')
            ->with('PZ', '2026', '07')
            ->willReturn(3);

        $result = $this->service->generateNumber($receipt);

        self::assertSame($receipt, $result);
        self::assertSame(4, $receipt->getNumber());
        self::assertSame('PZ/2026/07/0004', $receipt->getFullNumber());
    }

    public function testGenerateNumberUsesReleasePrefix(): void
    {
        $release = ReleaseFactory::createOne(['documentDate' => new \DateTimeImmutable('2026-01-05')]);

        $this->operationRepository->expects($this->once())
            ->method('getLastNumber')
            ->with('WZ', '2026', '01')
            ->willReturn(0);

        $this->service->generateNumber($release);

        self::assertSame(1, $release->getNumber());
        self::assertSame('WZ/2026/01/0001', $release->getFullNumber());
    }

    public function testGenerateNumberUsesRelocationPrefix(): void
    {
        $relocation = RelocationFactory::createOne(['documentDate' => new \DateTimeImmutable('2026-03-10')]);

        $this->operationRepository->expects($this->once())->method('getLastNumber')->willReturn(9);

        $this->service->generateNumber($relocation);

        self::assertSame('MM/2026/03/0010', $relocation->getFullNumber());
    }

    public function testGenerateNumberUsesAdjustmentPrefix(): void
    {
        $adjustment = AdjustmentFactory::createOne(['documentDate' => new \DateTimeImmutable('2026-06-01')]);

        $this->operationRepository->expects($this->once())->method('getLastNumber')->willReturn(0);

        $this->service->generateNumber($adjustment);

        self::assertSame('INW/2026/06/0001', $adjustment->getFullNumber());
    }

    public function testGenerateNumberUsesCorrectionPrefixOfCorrectedOperation(): void
    {
        $receipt = ReceiptFactory::createOne();
        $correction = CorrectionFactory::createOne([
            'documentDate' => new \DateTimeImmutable('2026-02-20'),
            'correctedOperation' => $receipt,
        ]);

        $this->operationRepository->expects($this->once())
            ->method('getLastNumber')
            ->with('KPZ', '2026', '02')
            ->willReturn(0);

        $this->service->generateNumber($correction);

        self::assertSame('KPZ/2026/02/0001', $correction->getFullNumber());
    }

    public function testGenerateNumberThrowsWhenCorrectionHasNoCorrectedOperation(): void
    {
        $correction = CorrectionFactory::createOne(['correctedOperation' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot generate a number for a Correction without a corrected operation.');

        $this->service->generateNumber($correction);
    }

    public function testGenerateNumberThrowsForCorrectionOfUnknownDocumentType(): void
    {
        $unknownCorrected = $this->createStub(Operation::class);
        $unknownCorrected->method('getDocumentType')->willReturn('unknown');

        $correction = CorrectionFactory::createOne(['correctedOperation' => $unknownCorrected]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid corrected document type: unknown');

        $this->service->generateNumber($correction);
    }

    public function testGenerateNumberThrowsForUnknownDocumentType(): void
    {
        $operation = $this->createStub(Operation::class);
        $operation->method('getNumber')->willReturn(null);
        $operation->method('getDocumentType')->willReturn('unknown');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document type: unknown');

        $this->service->generateNumber($operation);
    }

    public function testGenerateNumberPadsToFourDigitsWithoutTruncatingLargerNumbers(): void
    {
        $receipt = ReceiptFactory::createOne(['documentDate' => new \DateTimeImmutable('2026-01-01')]);

        $this->operationRepository->expects($this->once())->method('getLastNumber')->willReturn(12344);

        $this->service->generateNumber($receipt);

        self::assertSame('PZ/2026/01/12345', $receipt->getFullNumber());
    }

    public function testConfirmThrowsWhenStatusIsNotDraft(): void
    {
        $receipt = ReceiptFactory::createOne();
        $receipt->setStatus(OperationStatus::CONFIRMED);

        $this->operationRepository->expects($this->never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Operacja musi mieć status DRAFT.');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmThrowsWhenDocumentDateIsMissing(): void
    {
        $receipt = ReceiptFactory::createOne(['documentDate' => null]);

        $this->operationRepository->expects($this->never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Data dokumentu jest wymagana do zatwierdzenia.');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmReceiptThrowsWhenSupplierMissing(): void
    {
        $receipt = ReceiptFactory::createOne(['supplier' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Dostawca jest wymagany do zatwierdzenia przyjęcia.');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmReceiptThrowsWhenLineQuantityMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => force(null),
            'locationTo' => LocationFactory::createOne(),
            'unitPrice' => '10.00',
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ilość jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmReceiptThrowsWhenLineLocationToMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationTo' => null,
            'unitPrice' => '10.00',
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja docelowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmReceiptThrowsWhenLineUnitPriceMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationTo' => LocationFactory::createOne(),
            'unitPrice' => null,
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cena jednostkowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($receipt, UserFactory::createOne());
    }

    public function testConfirmReceiptAddsStockForEachLineAndMarksConfirmed(): void
    {
        $productA = ProductFactory::createOne();
        $productB = ProductFactory::createOne();
        $locationA = LocationFactory::createOne();
        $locationB = LocationFactory::createOne();

        $receipt = ReceiptFactory::createOne();
        $receipt->addOperationLine(OperationLineFactory::createOne([
            'product' => $productA,
            'quantity' => '5.000',
            'locationTo' => $locationA,
            'unitPrice' => '10.00',
        ]));
        $receipt->addOperationLine(OperationLineFactory::createOne([
            'product' => $productB,
            'quantity' => '3.000',
            'locationTo' => $locationB,
            'unitPrice' => '20.00',
        ]));

        $calls = [];
        $this->stockService->expects($this->exactly(2))->method('add')->willReturnCallback(function ($product, $location, $quantity) use (&$calls): void {
            $calls[] = [$product, $location, $quantity];
        });

        $this->operationRepository->expects($this->once())->method('save')->with($receipt, true);

        $user = UserFactory::createOne();
        $result = $this->service->confirm($receipt, $user);

        self::assertSame($receipt, $result);
        self::assertSame(OperationStatus::CONFIRMED, $receipt->getStatus());
        self::assertSame($user, $receipt->getConfirmedBy());
        self::assertInstanceOf(\DateTimeImmutable::class, $receipt->getConfirmedAt());
        self::assertSame([
            [$productA, $locationA, '5.000'],
            [$productB, $locationB, '3.000'],
        ], $calls);
    }

    public function testConfirmReleaseThrowsWhenRecipientMissing(): void
    {
        $release = ReleaseFactory::createOne(['recipient' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Odbiorca jest wymagany do zatwierdzenia wydania.');

        $this->service->confirm($release, UserFactory::createOne());
    }

    public function testConfirmReleaseThrowsWhenReleaseDateMissing(): void
    {
        $release = ReleaseFactory::createOne(['releaseDate' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Data wydania jest wymagana do zatwierdzenia wydania.');

        $this->service->confirm($release, UserFactory::createOne());
    }

    public function testConfirmReleaseThrowsWhenLineQuantityMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $release = ReleaseFactory::createOne();
        $release->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => force(null),
            'locationFrom' => LocationFactory::createOne(),
            'unitPrice' => '10.00',
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ilość jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($release, UserFactory::createOne());
    }

    public function testConfirmReleaseThrowsWhenLineLocationFromMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $release = ReleaseFactory::createOne();
        $release->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationFrom' => null,
            'unitPrice' => '10.00',
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja źródłowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($release, UserFactory::createOne());
    }

    public function testConfirmReleaseThrowsWhenLineUnitPriceMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $release = ReleaseFactory::createOne();
        $release->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationFrom' => LocationFactory::createOne(),
            'unitPrice' => null,
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cena jednostkowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($release, UserFactory::createOne());
    }

    public function testConfirmReleaseSubtractsStockForEachLine(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();
        $release = ReleaseFactory::createOne();
        $release->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '3.000',
            'locationFrom' => $location,
            'unitPrice' => '15.00',
        ]));

        $this->stockService->expects($this->once())->method('subtract')->with($product, $location, '3.000');
        $this->operationRepository->expects($this->once())->method('save')->with($release, true);

        $user = UserFactory::createOne();
        $this->service->confirm($release, $user);

        self::assertSame(OperationStatus::CONFIRMED, $release->getStatus());
        self::assertSame($user, $release->getConfirmedBy());
    }

    public function testConfirmRelocationThrowsWhenLineQuantityMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => force(null),
            'locationFrom' => LocationFactory::createOne(),
            'locationTo' => LocationFactory::createOne(),
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ilość jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($relocation, UserFactory::createOne());
    }

    public function testConfirmRelocationThrowsWhenLocationFromMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationTo' => LocationFactory::createOne(),
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja źródłowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($relocation, UserFactory::createOne());
    }

    public function testConfirmRelocationThrowsWhenLocationToMissing(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationFrom' => LocationFactory::createOne(),
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja docelowa jest wymagana dla pozycji "Śruba M6".');

        $this->service->confirm($relocation, UserFactory::createOne());
    }

    public function testConfirmRelocationThrowsWhenSourceAndDestinationAreTheSame(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $location = LocationFactory::createOne();
        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationFrom' => $location,
            'locationTo' => $location,
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja źródłowa i docelowa nie mogą być takie same dla pozycji "Śruba M6".');

        $this->service->confirm($relocation, UserFactory::createOne());
    }

    public function testConfirmRelocationSubtractsFromSourceThenAddsToDestinationPerLine(): void
    {
        $product = ProductFactory::createOne();
        $locationFrom = LocationFactory::createOne();
        $locationTo = LocationFactory::createOne();

        $relocation = RelocationFactory::createOne();
        $relocation->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '4.000',
            'locationFrom' => $locationFrom,
            'locationTo' => $locationTo,
        ]));

        $calls = [];
        $this->stockService->expects($this->once())->method('subtract')->willReturnCallback(function ($p, $l, $q) use (&$calls): void {
            $calls[] = ['subtract', $q];
        });
        $this->stockService->expects($this->once())->method('add')->willReturnCallback(function ($p, $l, $q) use (&$calls): void {
            $calls[] = ['add', $q];
        });

        $this->service->confirm($relocation, UserFactory::createOne());

        self::assertSame([
            ['subtract', '4.000'],
            ['add', '4.000'],
        ], $calls);
    }

    public function testConfirmAdjustmentThrowsWhenLineHasBothLocations(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $adjustment = AdjustmentFactory::createOne();
        $adjustment->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
            'locationFrom' => LocationFactory::createOne(),
            'locationTo' => LocationFactory::createOne(),
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pozycja "Śruba M6" musi mieć ustawioną dokładnie jedną lokalizację (źródłową lub docelową).');

        $this->service->confirm($adjustment, UserFactory::createOne());
    }

    public function testConfirmAdjustmentThrowsWhenLineHasNeitherLocation(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $adjustment = AdjustmentFactory::createOne();
        $adjustment->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '1.000',
        ]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pozycja "Śruba M6" musi mieć ustawioną dokładnie jedną lokalizację (źródłową lub docelową).');

        $this->service->confirm($adjustment, UserFactory::createOne());
    }

    public function testConfirmAdjustmentSkipsLineValidationWhenQuantityMissingButCrashesOnExecution(): void
    {
        $adjustment = AdjustmentFactory::createOne();
        $adjustment->addOperationLine(OperationLineFactory::createOne([
            'quantity' => force(null),
            'locationFrom' => LocationFactory::createOne(),
            'locationTo' => LocationFactory::createOne(),
        ]));

        $this->expectException(\TypeError::class);

        $this->service->confirm($adjustment, UserFactory::createOne());
    }

    public function testConfirmAdjustmentAddsStockWhenLineHasDestinationLocation(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();
        $adjustment = AdjustmentFactory::createOne();
        $adjustment->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '2.500',
            'locationTo' => $location,
        ]));

        $this->stockService->expects($this->once())->method('add')->with($product, $location, '2.500');
        $this->stockService->expects($this->never())->method('subtract');

        $this->service->confirm($adjustment, UserFactory::createOne());
    }

    public function testConfirmAdjustmentSubtractsStockWhenLineHasSourceLocation(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();
        $adjustment = AdjustmentFactory::createOne();
        $adjustment->addOperationLine(OperationLineFactory::createOne([
            'product' => $product,
            'quantity' => '2.500',
            'locationFrom' => $location,
        ]));

        $this->stockService->expects($this->once())->method('subtract')->with($product, $location, '2.500');
        $this->stockService->expects($this->never())->method('add');

        $this->service->confirm($adjustment, UserFactory::createOne());
    }

    public function testConfirmCorrectionDelegatesValidationAndConfirmationToCorrectionService(): void
    {
        $correction = CorrectionFactory::createOne();

        $this->correctionService->expects($this->once())->method('validateForConfirmation')->with($correction);
        $this->correctionService->expects($this->once())->method('confirm')->with($correction);
        $this->stockService->expects($this->never())->method('add');
        $this->stockService->expects($this->never())->method('subtract');
        $this->operationRepository->expects($this->once())->method('save')->with($correction, true);

        $user = UserFactory::createOne();
        $this->service->confirm($correction, $user);

        self::assertSame(OperationStatus::CONFIRMED, $correction->getStatus());
        self::assertSame($user, $correction->getConfirmedBy());
    }

    public function testConfirmCorrectionPropagatesValidationFailureFromCorrectionService(): void
    {
        $correction = CorrectionFactory::createOne();

        $this->correctionService->method('validateForConfirmation')
            ->willThrowException(new \DomainException('Korekta musi zawierać co najmniej jedną pozycję.'));
        $this->correctionService->expects($this->never())->method('confirm');
        $this->operationRepository->expects($this->never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Korekta musi zawierać co najmniej jedną pozycję.');

        $this->service->confirm($correction, UserFactory::createOne());
    }
}
