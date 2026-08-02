<?php

namespace App\Tests\Factory;

use App\Entity\Stocktaking;
use App\Enum\StocktakingStatus;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Stocktaking>
 */
final class StocktakingFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Stocktaking::class;
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
            'status' => StocktakingStatus::OPEN,
        ];
    }
}
