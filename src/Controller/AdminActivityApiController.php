<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminDirectoryService;
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
        $to = $this->parseDate($request->query->getString('to'));

        $pagination = $paginator->paginate(
            $adminDirectoryService->activityFeedQueryBuilder(
                $type === '' ? null : $type,
                $search === '' ? null : $search,
                $from,
                $to,
            ),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 25),
        );

        $response = new JsonResponse();
        $response->setData([
            'activity' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
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
