<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EnquiryService;
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

final class AdminEnquiryApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/enquiries',
        name: 'api_admin_enquiry_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        EnquiryService $enquiryService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $fromTimestamp = $request->query->getInt('from');
        $from = $fromTimestamp > 0 ? new DateTime()->setTimestamp($fromTimestamp) : null;
        $toTimestamp = $request->query->getInt('to');
        $to = $toTimestamp > 0 ? new DateTime()->setTimestamp($toTimestamp) : null;

        $pagination = $paginator->paginate(
            $enquiryService->enquiriesQueryBuilder($from, $to),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 10)),
        );

        $response = new JsonResponse();
        $response->setData([
            'enquiries' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }
}
