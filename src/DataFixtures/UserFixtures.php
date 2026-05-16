<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const REFERENCE_MANAGER = 'user_manager';
    public const REFERENCE_EMPLOYEE = 'user_employee';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createUser('manager', 'manager123', [User::ROLE_WAREHOUSE_MANAGER], self::REFERENCE_MANAGER));
        $manager->persist($this->createUser('pracownik', 'pracownik123', [User::ROLE_WAREHOUSE_EMPLOYEE], self::REFERENCE_EMPLOYEE));

        $manager->flush();
    }

    private function createUser(string $username, string $plainPassword, array $roles, string $reference): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setRoles($roles)
            ->setIsActive(true);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->addReference($reference, $user);

        return $user;
    }
}
