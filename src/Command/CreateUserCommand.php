<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new system user',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $io->ask('Username');
        $password = $io->askHidden('Password');
        $role = $io->choice('Role', ['ROLE_WAREHOUSE_EMPLOYEE', 'ROLE_WAREHOUSE_MANAGER'], 'ROLE_WAREHOUSE_EMPLOYEE');

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setRoles([$role]);
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created with role %s.', $username, $role));

        return Command::SUCCESS;
    }
}
