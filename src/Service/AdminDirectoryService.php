<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Enum\UserStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

/**
 * Read-only admin views over the user base: the student directory, per-student
 * summaries, and the UserLog activity feed.
 */
class AdminDirectoryService
{
    private const int RECENT_ACTIVITY_LIMIT = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserLogRepository $userLogRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function studentsQueryBuilder(?string $search, ?UserStatus $status): QueryBuilder
    {
        return $this->userRepository->createAdminQueryBuilder($search, $status);
    }

    public function findStudent(Ulid $publicId): ?User
    {
        return $this->userRepository->findOneByPublicId($publicId);
    }

    public function facilitatorsQueryBuilder(?string $search, ?UserStatus $status): QueryBuilder
    {
        return $this->userRepository->createFacilitatorQueryBuilder($search, $status);
    }

    public function findFacilitator(Ulid $publicId): ?User
    {
        return $this->userRepository->findOneByPublicId($publicId);
    }

    /**
     * Scalar summary for a single student: enrollments, lifetime spend and the
     * current subscription status.
     *
     * @return array<string, mixed>
     */
    public function studentSummary(User $student): array
    {
        $connection = $this->entityManager->getConnection();

        $enrollments = $connection->executeQuery(
            "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'completed'), 0) AS completed
             FROM enrollments WHERE student_id = :id",
            ['id' => $student->getId()],
        )->fetchAssociative();

        $orders = $connection->executeQuery(
            "SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_amount_cents ELSE 0 END), 0) AS spent_cents
             FROM orders WHERE user_id = :id",
            ['id' => $student->getId()],
        )->fetchAssociative();

        $subscription = $this->subscriptionRepository->findCurrentByUser($student);

        return [
            'enrollments' => [
                'total' => (int) $enrollments['total'],
                'completed' => (int) $enrollments['completed'],
            ],
            'orders' => [
                'total' => (int) $orders['total'],
                'total_spent_cents' => (int) $orders['spent_cents'],
                'currency' => 'ZAR',
            ],
            'subscription_status' => $subscription instanceof Subscription ? $subscription->getStatus()->value : null,
        ];
    }

    /**
     * @return \App\Entity\UserLog[]
     */
    public function recentActivity(User $student): array
    {
        return $this->userLogRepository->findRecentByUser($student, self::RECENT_ACTIVITY_LIMIT);
    }

    public function activityFeedQueryBuilder(?string $typeSlug, ?string $search, ?DateTime $from, ?DateTime $to): QueryBuilder
    {
        return $this->userLogRepository->createFeedQueryBuilder($typeSlug, $search, $from, $to);
    }
}
