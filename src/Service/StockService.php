<?php

namespace App\Service;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\Stock;
use App\Repository\StockRepository;

class StockService
{
    private array $cache = [];

    public function __construct(
        private readonly StockRepository $stockRepository,
    ) {
    }

    public function add(
        Product $product,
        Location $location,
        string $quantity,
        bool $flush = false,
    ) {
        $stock = $this->getStock($product, $location);
        $newQuantity = bcadd($stock->getQuantity(), $quantity, Stock::QUANTITY_SCALE);
        $stock->setQuantity($newQuantity);

        $this->stockRepository->save($stock, $flush);
    }

    public function subtract(
        Product $product,
        Location $location,
        string $quantity,
        bool $flush = false,
    ) {
        $stock = $this->getStock($product, $location);
        $newQuantity = bcsub($stock->getQuantity(), $quantity, Stock::QUANTITY_SCALE);

        if (bccomp($newQuantity, '0', Stock::QUANTITY_SCALE) < 0) {
            throw new \DomainException(sprintf('Niewystarczająca ilość produktu "%s" w lokalizacji "%s". Dostępna: %s, żądana: %s.', $product->getName(), $location->getName(), $stock->getQuantity(), $quantity));
        }

        $stock->setQuantity($newQuantity);

        if (0 === bccomp($newQuantity, '0', Stock::QUANTITY_SCALE)) {
            $this->removeStock($stock, $flush);
        } else {
            $this->stockRepository->save($stock, $flush);
        }
    }

    private function getStock(
        Product $product,
        Location $location,
    ): Stock {
        $cacheKey = $product->getId().'_'.$location->getId();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $stock = $this->stockRepository->findOneBy([
            'product' => $product,
            'location' => $location,
        ]);

        if (null === $stock) {
            $stock = (new Stock())
                ->setProduct($product)
                ->setLocation($location);
        }

        $this->cache[$cacheKey] = $stock;

        return $stock;
    }
}
