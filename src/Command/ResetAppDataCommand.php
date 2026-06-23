<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BlogCategory;
use App\Entity\BlogPost;
use App\Entity\Bundle;
use App\Entity\Certificate;
use App\Entity\Choice;
use App\Entity\CommunityComment;
use App\Entity\CommunityPost;
use App\Entity\CommunityPostLike;
use App\Entity\Coupon;
use App\Entity\CouponRedemption;
use App\Entity\Course;
use App\Entity\EmailCampaign;
use App\Entity\Enrollment;
use App\Entity\Entitlement;
use App\Entity\Lesson;
use App\Entity\LessonProgress;
use App\Entity\NewsletterSubscription;
use App\Entity\NotificationEmail;
use App\Entity\Order;
use App\Entity\PayfastWebhookEvent;
use App\Entity\PaymentMethod;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\RefreshToken;
use App\Entity\RefundRequest;
use App\Entity\Review;
use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Entity\UserLog;
use App\Entity\VerificationCode;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:reset',
    description: 'Delete all user, course and operational data while preserving seeded reference data (categories, subscription plans, log types, docs users).',
)]
final class ResetAppDataCommand extends AbstractCommand
{
    /**
     * Entities wiped in child-to-parent order so foreign keys stay satisfied.
     * Seeded/reference data (Category, SubscriptionPlan, UserLogType, DocsUser)
     * is intentionally excluded; UserProfile is cascade-removed with its User.
     *
     * @var list<class-string>
     */
    private const array DELETION_ORDER = [
        Choice::class,
        Question::class,
        QuizAttempt::class,
        Quiz::class,
        LessonProgress::class,
        Lesson::class,
        Certificate::class,
        Review::class,
        CommunityPostLike::class,
        CommunityComment::class,
        CommunityPost::class,
        BlogPost::class,
        BlogCategory::class,
        CouponRedemption::class,
        RefundRequest::class,
        SubscriptionPayment::class,
        Subscription::class,
        Entitlement::class,
        Order::class,
        Enrollment::class,
        PaymentMethod::class,
        Coupon::class,
        Bundle::class,
        Course::class,
        EmailCampaign::class,
        NewsletterSubscription::class,
        NotificationEmail::class,
        PayfastWebhookEvent::class,
        RefreshToken::class,
        VerificationCode::class,
        UserLog::class,
        User::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip the interactive confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')
            && !$io->confirm('This permanently deletes all users, courses and operational data. Continue?', false)) {
            $io->warning('Aborted; no data was deleted.');

            return self::SUCCESS;
        }

        $this->getLogger()->info('Starting application data reset');

        try {
            $this->entityManager->beginTransaction();

            $totalDeleted = 0;
            foreach (self::DELETION_ORDER as $entityClass) {
                $entities = $this->entityManager->getRepository($entityClass)->findAll();

                foreach ($entities as $entity) {
                    $this->entityManager->remove($entity);
                }

                $this->entityManager->flush();

                $shortName = new ReflectionClass($entityClass)->getShortName();
                $totalDeleted += count($entities);
                $this->getLogger()->info(sprintf('Deleted %d %s record(s)', count($entities), $shortName));
            }

            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            $this->getLogger()->error(sprintf('Application data reset failed: %s', $exception->getMessage()));
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->getLogger()->info(sprintf('Application data reset completed; deleted %d record(s)', $totalDeleted));
        $io->success(sprintf('Reset complete. Deleted %d record(s) across %d tables.', $totalDeleted, count(self::DELETION_ORDER)));

        return self::SUCCESS;
    }
}
