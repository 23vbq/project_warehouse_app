<?php

namespace App\Tests\Factory;

use App\Entity\Release;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Release>
 */
final class ReleaseFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Release::class;
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
            'recipient' => self::faker()->company(),
            'releaseDate' => new \DateTimeImmutable(),
        ];
    }
}
