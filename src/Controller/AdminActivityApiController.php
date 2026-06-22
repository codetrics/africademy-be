<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminDirectoryService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use DateTime;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminActivityApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/activity',
        name: 'api_admin_activity_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        AdminDirectoryService $adminDirectoryService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $type = $request->query->getString('type');
        $search = $request->query->getString('q');
        $from = $this->parseDate($request->query->getString('from'));
        $to = $this->parseDate($request->query->getString('to'), true);

        $pagination = $paginator->paginate(
            $adminDirectoryService->activityFeedQueryBuilder(
                $type === '' ? null : $type,
                $search === '' ? null : $search,
                $from,
                $to,
            ),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 25)),
        );

        $response = new JsonResponse();
        $response->setData([
            'activity' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    private function parseDate(string $value, bool $endOfDay = false): ?DateTime
    {
        if ($value === '') {
            return null;
        }

        // The leading '!' resets the time to 00:00:00 (otherwise createFromFormat
        // fills in the current time, silently excluding part of the day).
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        if (!$date instanceof DateTime) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date;
    }
}
