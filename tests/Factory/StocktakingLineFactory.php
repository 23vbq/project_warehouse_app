<?php

namespace App\Tests\Factory;

use App\Entity\StocktakingLine;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<StocktakingLine>
 */
final class StocktakingLineFactory extends ObjectFactory
{
    public static function class(): string
    {
        return StocktakingLine::class;
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
            'product' => ProductFactory::new(),
            'location' => LocationFactory::new(),
            'expectedQuantity' => '0.000',
        ];
    }
}
