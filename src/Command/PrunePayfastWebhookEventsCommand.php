<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PayfastWebhookEventRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:payfast:prune-webhook-events',
    description: 'Delete stored PayFast ITN audit records past the retention window.',
)]
final class PrunePayfastWebhookEventsCommand extends AbstractCommand
{
    public const int RETENTION_DAYS = 90;
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly PayfastWebhookEventRepository $payfastWebhookEventRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new DateTime(sprintf('-%d days', self::RETENTION_DAYS));

        $deleted = 0;
        do {
            $events = $this->payfastWebhookEventRepository->findReceivedBefore($threshold, self::BATCH_SIZE);

            foreach ($events as $event) {
                $this->payfastWebhookEventRepository->remove($event);
            }
            $this->entityManager->flush();

            $deleted += count($events);
        } while (count($events) === self::BATCH_SIZE);

        $this->getLogger()->info(sprintf('Pruned %d PayFast webhook event(s) older than %d days', $deleted, self::RETENTION_DAYS));
        $io->success(sprintf('Pruned %d PayFast webhook event(s).', $deleted));

        return self::SUCCESS;
    }
}
