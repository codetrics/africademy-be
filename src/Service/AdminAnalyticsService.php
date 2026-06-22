<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Enum\CourseStatus;
use App\Enum\SubscriptionStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read-only reporting aggregates for the admin dashboard. Uses parameterised
 * native SQL for cross-table sums and date-bucketed time series, which the
 * QueryBuilder cannot express without extra DQL functions.
 */
class AdminAnalyticsService
{
    private const int RECENT_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $connection = $this->entityManager->getConnection();
        $recentCutoff = new DateTime()->modify(sprintf('-%d days', self::RECENT_DAYS))->format('Y-m-d H:i:s');

        $revenueTotalCents = (int) $connection->executeQuery(
            "SELECT COALESCE((SELECT SUM(amount_amount_cents) FROM orders WHERE status = 'paid'), 0)
                  + COALESCE((SELECT SUM(amount_amount_cents) FROM subscription_payments WHERE status = 'paid'), 0)",
        )->fetchOne();

        $revenueRecentCents = (int) $connection->executeQuery(
            "SELECT COALESCE((SELECT SUM(amount_amount_cents) FROM orders WHERE status = 'paid' AND paid_at >= :cutoff), 0)
                  + COALESCE((SELECT SUM(amount_amount_cents) FROM subscription_payments WHERE status = 'paid' AND attempted_at >= :cutoff), 0)",
            ['cutoff' => $recentCutoff],
        )->fetchOne();

        $refundedCents = (int) $connection->executeQuery(
            "SELECT COALESCE(SUM(amount_amount_cents), 0) FROM orders WHERE status = 'refunded'",
        )->fetchOne();

        // Sum exact monthly-equivalent cents (annual / 12 kept as a decimal) and
        // round once at the end so per-plan rounding can't drift the total.
        $mrrCents = (int) round((float) $connection->executeQuery(
            "SELECT COALESCE(SUM(CASE WHEN plan.billing_interval = 'annual' THEN plan.price_amount_cents / 12 ELSE plan.price_amount_cents END), 0)
             FROM subscriptions sub
             INNER JOIN subscription_plans plan ON plan.id = sub.plan_id
             WHERE sub.status = 'active'",
        )->fetchOne());

        return [
            'users' => [
                'total' => (int) $connection->executeQuery('SELECT COUNT(*) FROM users')->fetchOne(),
                'new_last_30_days' => (int) $connection->executeQuery(
                    'SELECT COUNT(*) FROM users WHERE created_at >= :cutoff',
                    ['cutoff' => $recentCutoff],
                )->fetchOne(),
                'verified' => (int) $connection->executeQuery(
                    'SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL',
                )->fetchOne(),
            ],
            'revenue' => [
                'currency' => 'ZAR',
                'total_cents' => $revenueTotalCents,
                'last_30_days_cents' => $revenueRecentCents,
                'refunded_cents' => $refundedCents,
                'mrr_cents' => $mrrCents,
            ],
            'subscriptions' => $this->countsByStatus('subscriptions', SubscriptionStatus::cases()),
            'enrollments' => [
                'total' => (int) $connection->executeQuery('SELECT COUNT(*) FROM enrollments')->fetchOne(),
                'completed' => (int) $connection->executeQuery(
                    "SELECT COUNT(*) FROM enrollments WHERE status = 'completed'",
                )->fetchOne(),
            ],
            'courses' => [
                'published' => (int) $connection->executeQuery(
                    "SELECT COUNT(*) FROM courses WHERE status = 'published'",
                )->fetchOne(),
            ],
            'reviews' => [
                'average_rating' => round((float) $connection->executeQuery(
                    'SELECT COALESCE(AVG(rating), 0) FROM reviews',
                )->fetchOne(), 2),
            ],
            'refund_requests' => [
                'pending' => (int) $connection->executeQuery(
                    "SELECT COUNT(*) FROM refund_requests WHERE status = 'pending'",
                )->fetchOne(),
            ],
        ];
    }

    /**
     * Paid revenue (orders + subscription payments) bucketed by day or month.
     *
     * @return array<int, array{bucket: string, amount_cents: int}>
     */
    public function revenueSeries(DateTime $from, DateTime $to, string $interval): array
    {
        $format = $interval === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $rows = $this->entityManager->getConnection()->executeQuery(
            "SELECT bucket, SUM(amount_cents) AS amount_cents FROM (
                SELECT DATE_FORMAT(paid_at, :format) AS bucket, amount_amount_cents AS amount_cents
                FROM orders WHERE status = 'paid' AND paid_at BETWEEN :from AND :to
                UNION ALL
                SELECT DATE_FORMAT(attempted_at, :format) AS bucket, amount_amount_cents AS amount_cents
                FROM subscription_payments WHERE status = 'paid' AND attempted_at BETWEEN :from AND :to
             ) series
             GROUP BY bucket ORDER BY bucket",
            [
                'format' => $format,
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ],
        )->fetchAllAssociative();

        $mapped = array_map(
            static fn (array $row): array => ['bucket' => (string) $row['bucket'], 'amount_cents' => (int) $row['amount_cents']],
            $rows,
        );

        return $this->fillSeriesGaps($mapped, $from, $to, $interval, 'amount_cents');
    }

    /**
     * New enrollments bucketed by day or month.
     *
     * @return array<int, array{bucket: string, count: int}>
     */
    public function enrollmentSeries(DateTime $from, DateTime $to, string $interval): array
    {
        $format = $interval === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $rows = $this->entityManager->getConnection()->executeQuery(
            'SELECT DATE_FORMAT(created_at, :format) AS bucket, COUNT(*) AS total
             FROM enrollments WHERE created_at BETWEEN :from AND :to
             GROUP BY bucket ORDER BY bucket',
            [
                'format' => $format,
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ],
        )->fetchAllAssociative();

        $mapped = array_map(
            static fn (array $row): array => ['bucket' => (string) $row['bucket'], 'count' => (int) $row['total']],
            $rows,
        );

        return $this->fillSeriesGaps($mapped, $from, $to, $interval, 'count');
    }

    /**
     * Fills empty buckets across the full [from, to] range with a zero value so
     * the series is continuous (charts don't gap-collapse periods with no data).
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function fillSeriesGaps(array $rows, DateTime $from, DateTime $to, string $interval, string $valueKey): array
    {
        $byBucket = [];
        foreach ($rows as $row) {
            $byBucket[(string) $row['bucket']] = $row[$valueKey];
        }

        $format = $interval === 'month' ? 'Y-m' : 'Y-m-d';
        $step = $interval === 'month' ? '+1 month' : '+1 day';

        $cursor = $interval === 'month'
            ? new DateTime($from->format('Y-m-01'))
            : new DateTime($from->format('Y-m-d'));
        $end = clone $to;

        $series = [];
        while ($cursor <= $end) {
            $bucket = $cursor->format($format);
            $series[] = ['bucket' => $bucket, $valueKey => $byBucket[$bucket] ?? 0];
            $cursor = new DateTime($cursor->format('Y-m-d H:i:s'))->modify($step);
        }

        return $series;
    }

    /**
     * Published courses ranked by enrollment count or rating.
     *
     * @return Course[]
     */
    public function topCourses(string $by, int $limit): array
    {
        $orderField = $by === 'rating' ? 'course.ratingAverage' : 'course.enrollmentCount';

        return $this->entityManager->getRepository(Course::class)
            ->createQueryBuilder('course')
            ->where('course.status = :status')
            ->setParameter('status', CourseStatus::Published)
            ->orderBy($orderField, 'DESC')
            ->addOrderBy('course.enrollmentCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \BackedEnum[] $seedCases status cases pre-seeded to 0 so the result is
     *                                 always a populated keyed object (never an empty array)
     *
     * @return array<string, int>
     */
    private function countsByStatus(string $table, array $seedCases = []): array
    {
        $counts = [];
        foreach ($seedCases as $case) {
            $counts[(string) $case->value] = 0;
        }

        $rows = $this->entityManager->getConnection()->executeQuery(
            sprintf('SELECT status, COUNT(*) AS total FROM %s GROUP BY status', $table),
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
