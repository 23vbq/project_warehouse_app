<?php

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<User>
 */
final class UserFactory extends ObjectFactory
{
    public static function class(): string
    {
        return User::class;
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
            'username' => self::faker()->unique()->userName(),
            'password' => 'hashed-password',
            'isActive' => true,
            'roles' => [],
        ];
    }
}
