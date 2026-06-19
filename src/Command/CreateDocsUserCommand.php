<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DocsUser;
use App\Repository\DocsUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:docs-user:create',
    description: 'Create the single docs user that gates Swagger UI access. Fails if one already exists.',
)]
final class CreateDocsUserCommand extends AbstractCommand
{
    public function __construct(
        private readonly DocsUserRepository $docsUserRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the docs login')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password (prompted securely if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->docsUserRepository->countAll() > 0) {
            $io->error('A docs user already exists; only one is allowed.');

            return self::FAILURE;
        }

        $username = trim((string) $input->getArgument('username'));
        if ($username === '') {
            $io->error('Username cannot be blank.');

            return self::FAILURE;
        }

        $password = (string) ($input->getArgument('password') ?? '');
        if ($password === '') {
            $password = (string) $io->askHidden('Password');
        }

        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $docsUser = new DocsUser();
        $docsUser->setUsername($username);
        $docsUser->setPassword($this->passwordHasher->hashPassword($docsUser, $password));
        $this->docsUserRepository->save($docsUser, true);

        $this->getLogger()->info(sprintf('Created docs user "%s".', $username));
        $io->success(sprintf('Docs user "%s" created. Swagger UI now requires these credentials.', $username));

        return self::SUCCESS;
    }
}
