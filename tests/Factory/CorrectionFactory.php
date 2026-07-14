<?php

namespace App\Tests\Factory;

use App\Entity\Correction;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Correction>
 */
final class CorrectionFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Correction::class;
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
