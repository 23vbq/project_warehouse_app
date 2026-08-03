<?php

namespace App\Tests\Unit\Service;

use App\Entity\Stock;
use App\Repository\StockRepository;
use App\Service\StockService;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\ProductFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StockServiceTest extends TestCase
{
    public function testAddCreatesNewStockWhenNoneExists(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['product' => $product, 'location' => $location])
            ->willReturn(null);

        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Stock $stock) use ($product, $location) {
                self::assertSame($product, $stock->getProduct());
                self::assertSame($location, $stock->getLocation());
                self::assertSame('10.000', $stock->getQuantity());

                return true;
            }), false);

        (new StockService($repository))->add($product, $location, '10.000');
    }

    public function testAddAccumulatesExistingStock(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $existingStock = (new Stock())
            ->setProduct($product)
            ->setLocation($location)
            ->setQuantity('5.500');

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn($existingStock);

        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Stock $stock) => '8.750' === $stock->getQuantity()), false);

        (new StockService($repository))->add($product, $location, '3.250');
    }

    #[DataProvider('flushFlagProvider')]
    public function testAddPassesFlushFlagToRepository(bool $flush): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Stock::class), $flush);

        (new StockService($repository))->add($product, $location, '1.000', $flush);
    }

    public static function flushFlagProvider(): array
    {
        return [
            'flush enabled' => [true],
            'flush disabled' => [false],
        ];
    }

    public function testAddTruncatesQuantityToConfiguredScaleWithoutRounding(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Stock $stock) => '1.234' === $stock->getQuantity()), false);

        (new StockService($repository))->add($product, $location, '1.2345');
    }

    public function testSubtractDecreasesExistingStock(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $existingStock = (new Stock())
            ->setProduct($product)
            ->setLocation($location)
            ->setQuantity('10.000');

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn($existingStock);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Stock $stock) => '4.000' === $stock->getQuantity()), false);
        $repository->expects($this->never())->method('remove');

        (new StockService($repository))->subtract($product, $location, '6.000');
    }

    public function testSubtractToExactZeroRemovesStock(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $existingStock = (new Stock())
            ->setProduct($product)
            ->setLocation($location)
            ->setQuantity('5.000');

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn($existingStock);
        $repository->expects($this->once())->method('remove')->with($existingStock, false);
        $repository->expects($this->never())->method('save');

        (new StockService($repository))->subtract($product, $location, '5.000');
    }

    public function testSubtractZeroFromEmptyStockAlsoRemovesIt(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $existingStock = (new Stock())
            ->setProduct($product)
            ->setLocation($location);

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn($existingStock);
        $repository->expects($this->once())->method('remove');
        $repository->expects($this->never())->method('save');

        (new StockService($repository))->subtract($product, $location, '0.000');
    }

    public function testSubtractThrowsDescriptiveExceptionWhenInsufficientStock(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $location = LocationFactory::createOne(['name' => 'Regał A1']);

        $existingStock = (new Stock())
            ->setProduct($product)
            ->setLocation($location)
            ->setQuantity('3.000');

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn($existingStock);
        $repository->expects($this->never())->method('save');
        $repository->expects($this->never())->method('remove');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Niewystarczająca ilość produktu "Śruba M6" w lokalizacji "Regał A1". Dostępna: 3.000, żądana: 10.000.');

        (new StockService($repository))->subtract($product, $location, '10.000');
    }

    public function testSubtractThrowsWhenNoStockRecordExistsYet(): void
    {
        $product = ProductFactory::createOne(['name' => 'Śruba M6']);
        $location = LocationFactory::createOne(['name' => 'Regał A1']);

        $repository = $this->createMock(StockRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $repository->expects($this->never())->method('remove');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Dostępna: 0.000, żądana: 1.000.');

        (new StockService($repository))->subtract($product, $location, '1.000');
    }

    public function testAddUsesCacheAndQueriesRepositoryOnlyOnceForSamePair(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->expects($this->once())->method('findOneBy')->willReturn(null);

        $capturedStock = null;
        $repository->method('save')->willReturnCallback(function (Stock $stock) use (&$capturedStock): void {
            $capturedStock = $stock;
        });

        $service = new StockService($repository);
        $service->add($product, $location, '5.000');
        $service->add($product, $location, '3.000');

        self::assertSame('8.000', $capturedStock->getQuantity());
    }

    public function testAddDoesNotMixCacheBetweenDifferentProductLocationPairs(): void
    {
        $productA = ProductFactory::createOne();
        $productB = ProductFactory::createOne();
        $locationX = LocationFactory::createOne();
        $locationY = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->expects($this->exactly(3))->method('findOneBy')->willReturn(null);

        $savedQuantities = [];
        $repository->method('save')->willReturnCallback(function (Stock $stock) use (&$savedQuantities): void {
            $savedQuantities[] = $stock->getQuantity();
        });

        $service = new StockService($repository);
        $service->add($productA, $locationX, '5.000');
        $service->add($productA, $locationY, '5.000');
        $service->add($productB, $locationX, '5.000');

        self::assertSame(['5.000', '5.000', '5.000'], $savedQuantities);
    }

    public function testCacheEntryIsInvalidatedAfterStockIsRemoved(): void
    {
        $product = ProductFactory::createOne();
        $location = LocationFactory::createOne();

        $repository = $this->createMock(StockRepository::class);
        $repository->expects($this->exactly(2))->method('findOneBy')->willReturn(null);
        $repository->expects($this->once())->method('remove');
        $repository->expects($this->exactly(2))->method('save');

        $service = new StockService($repository);
        $service->add($product, $location, '5.000');
        $service->subtract($product, $location, '5.000');
        $service->add($product, $location, '2.000');
    }
}
