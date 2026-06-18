<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SubscriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscriptions:bill',
    description: 'Renew or expire subscriptions whose billing period has ended.',
)]
final class RunSubscriptionBillingCommand extends AbstractCommand
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $processed = $this->subscriptionService->renewDueSubscriptions();

        $this->getLogger()->info(sprintf('Processed %d due subscription(s)', $processed));
        $io->success(sprintf('Processed %d due subscription(s).', $processed));

        return self::SUCCESS;
    }
}
