<?php

namespace App\Tests\Factory;

use App\Entity\OperationLine;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<OperationLine>
 */
final class OperationLineFactory extends ObjectFactory
{
    public static function class(): string
    {
        return OperationLine::class;
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
            'quantity' => '1.000',
        ];
    }
}
