<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\JsonExceptionResponse;
use App\Service\AdminAnalyticsService;
use App\Service\SerializerService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $range = $this->range($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }
        [$from, $to] = $range;

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
        $range = $this->range($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }
        [$from, $to] = $range;

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
     * Resolves the from/to window from query params, defaulting to the last 30
     * days. Bounds are snapped to start/end of day so the whole `to` day is
     * included regardless of request time. Returns a 422 response if a date is
     * malformed or the range is inverted.
     *
     * @return array{0: DateTime, 1: DateTime}|JsonResponse
     */
    private function range(Request $request): array|JsonResponse
    {
        $fromValue = $request->query->getString('from');
        $toValue = $request->query->getString('to');

        $from = $fromValue === '' ? new DateTime()->modify('-30 days') : $this->parseDate($fromValue);
        $to = $toValue === '' ? new DateTime() : $this->parseDate($toValue);

        if (!$from instanceof DateTime || !$to instanceof DateTime) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Dates must be valid and formatted as YYYY-MM-DD.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $from->setTime(0, 0, 0);
        $to->setTime(23, 59, 59);

        if ($from > $to) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'The "from" date must not be after the "to" date.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return [$from, $to];
    }

    private function parseDate(string $value): ?DateTime
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        if (!$date instanceof DateTime || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
