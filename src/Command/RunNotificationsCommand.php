<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:run',
    description: 'Dispatch pending email notifications that are due.',
)]
final class RunNotificationsCommand extends AbstractCommand
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $processed = $this->notificationService->dispatchDue();

        $this->getLogger()->info(sprintf('Dispatched %d due notification(s)', $processed));
        $io->success(sprintf('Dispatched %d due notification(s).', $processed));

        return self::SUCCESS;
    }
}
