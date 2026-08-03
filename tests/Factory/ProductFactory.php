<?php

namespace App\Tests\Factory;

use App\Entity\Product;
use App\Enum\ProductType;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Product>
 */
final class ProductFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            Instantiator::withConstructor()->alwaysForce('id'),
        );
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => self::faker()->unique()->numberBetween(1, 1_000_000),
            'sku' => self::faker()->unique()->bothify('SKU-####'),
            'name' => self::faker()->words(2, true),
            'type' => ProductType::FINISHED,
            'unit' => 'szt',
            'unitPrice' => '10.00',
        ];
    }
}
