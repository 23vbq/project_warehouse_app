<?php

namespace App\Tests\Factory;

use App\Entity\Relocation;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Relocation>
 */
final class RelocationFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Relocation::class;
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
            'documentDate' => new \DateTimeImmutable(),
        ];
    }
}
