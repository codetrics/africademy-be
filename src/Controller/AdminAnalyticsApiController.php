<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAnalyticsService;
use App\Service\SerializerService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminAnalyticsApiController extends AbstractController
{
    private const int MAX_TOP_COURSES = 20;

    #[Route(
        '/api/{version}/admin/analytics/overview',
        name: 'api_admin_analytics_overview',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function overview(
        AdminAnalyticsService $adminAnalyticsService,
    ): JsonResponse {
        return new JsonResponse($adminAnalyticsService->overview());
    }

    #[Route(
        '/api/{version}/admin/analytics/revenue',
        name: 'api_admin_analytics_revenue',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function revenue(
        Request $request,
        AdminAnalyticsService $adminAnalyticsService,
    ): JsonResponse {
        $interval = $this->interval($request);
        [$from, $to] = $this->range($request);

        return new JsonResponse([
            'interval' => $interval,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'series' => $adminAnalyticsService->revenueSeries($from, $to, $interval),
        ]);
    }

    #[Route(
        '/api/{version}/admin/analytics/enrollments',
        name: 'api_admin_analytics_enrollments',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function enrollments(
        Request $request,
        AdminAnalyticsService $adminAnalyticsService,
    ): JsonResponse {
        $interval = $this->interval($request);
        [$from, $to] = $this->range($request);

        return new JsonResponse([
            'interval' => $interval,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'series' => $adminAnalyticsService->enrollmentSeries($from, $to, $interval),
        ]);
    }

    #[Route(
        '/api/{version}/admin/analytics/top-courses',
        name: 'api_admin_analytics_top_courses',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function topCourses(
        Request $request,
        AdminAnalyticsService $adminAnalyticsService,
        SerializerService $serializerService,
    ): JsonResponse {
        $by = $request->query->getString('by') === 'rating' ? 'rating' : 'enrollment';
        $limit = min(max($request->query->getInt('limit', 5), 1), self::MAX_TOP_COURSES);

        $response = new JsonResponse();
        $response->setData([
            'by' => $by,
            'courses' => json_decode($serializerService->serialize($adminAnalyticsService->topCourses($by, $limit))),
        ]);

        return $response;
    }

    private function interval(Request $request): string
    {
        return $request->query->getString('interval') === 'month' ? 'month' : 'day';
    }

    /**
     * Resolves the from/to window from query params, defaulting to the last 30 days.
     *
     * @return array{0: DateTime, 1: DateTime}
     */
    private function range(Request $request): array
    {
        $to = $this->parseDate($request->query->getString('to')) ?? new DateTime();
        $from = $this->parseDate($request->query->getString('from')) ?? new DateTime()->modify('-30 days');

        return [$from, $to];
    }

    private function parseDate(string $value): ?DateTime
    {
        if ($value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date instanceof DateTime ? $date : null;
    }
}
