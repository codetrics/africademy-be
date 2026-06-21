<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:grant-role',
    description: 'Grant or revoke a role on a user identified by email.',
)]
final class GrantRoleCommand extends AbstractCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of the target user')
            ->addArgument('role', InputArgument::REQUIRED, 'Role to grant, e.g. ROLE_TEACHER')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Revoke the role instead of granting it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $role = strtoupper((string) $input->getArgument('role'));
        $revoke = (bool) $input->getOption('revoke');

        $manageableRoles = [User::ROLE_STUDENT, User::ROLE_TEACHER, User::ROLE_ADMIN];

        if (!in_array($role, $manageableRoles, true)) {
            $io->error(sprintf('Invalid role "%s": allowed roles are %s.', $role, implode(', ', $manageableRoles)));

            return self::FAILURE;
        }

        $user = $this->userRepository->findOneByEmail($email);

        if (!$user instanceof User) {
            $io->error(sprintf('No user found with email "%s".', $email));

            return self::FAILURE;
        }

        $roles = $user->getRawRoles();

        if ($revoke) {
            $roles = array_values(array_filter($roles, static fn (string $existingRole): bool => $existingRole !== $role));
        } else {
            $roles[] = $role;
            $roles = array_values(array_unique($roles));
        }

        $user->setRoles($roles);
        $this->userRepository->save($user, true);

        $this->getLogger()->info(sprintf('%s role %s for %s', $revoke ? 'Revoked' : 'Granted', $role, $email));
        $io->success(sprintf('%s %s for %s.', $revoke ? 'Revoked' : 'Granted', $role, $email));

        return self::SUCCESS;
    }
}
