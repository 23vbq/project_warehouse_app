<?php

namespace App\Tests\Factory;

use App\Entity\Location;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Location>
 */
final class LocationFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Location::class;
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
            'code' => self::faker()->unique()->bothify('LOC-####'),
            'name' => self::faker()->words(2, true),
        ];
    }
}
