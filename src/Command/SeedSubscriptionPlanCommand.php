<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Money;
use App\Entity\SubscriptionPlan;
use App\Enum\SubscriptionInterval;
use App\Repository\SubscriptionPlanRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscription-plan:seed',
    description: 'Seed the default monthly subscription plan (idempotent).',
)]
final class SeedSubscriptionPlanCommand extends AbstractCommand
{
    private const string SLUG = 'premium-monthly';
    private const string NAME = 'Premium';
    private const int PRICE_CENTS = 15000;

    public function __construct(
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!is_null($this->subscriptionPlanRepository->findOneBySlug(self::SLUG))) {
            $io->success('Subscription plan already present.');

            return self::SUCCESS;
        }

        $plan = new SubscriptionPlan();
        $plan->setName(self::NAME);
        $plan->setSlug(self::SLUG);
        $plan->setInterval(SubscriptionInterval::Monthly);
        $plan->setPrice(new Money(self::PRICE_CENTS));
        $this->subscriptionPlanRepository->save($plan, true);

        $this->getLogger()->info('Seeded subscription plan ' . self::SLUG);
        $io->success('Seeded the Premium monthly subscription plan.');

        return self::SUCCESS;
    }
}
