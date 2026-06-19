<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\JsonExceptionResponse;
use App\Service\Helper\Tools;
use App\Exceptions\OrderException;
use App\Service\RefundService;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class AdminRefundApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/refund-requests',
        name: 'api_admin_refund_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        RefundService $refundService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $pagination = $paginator->paginate(
            $refundService->createPendingQueryBuilder(),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 10)),
        );

        $response = new JsonResponse();
        $response->setData([
            'refund_requests' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/refund-requests/{id}/approve',
        name: 'api_admin_refund_approve',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(
        Request $request,
        RefundService $refundService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $refundRequest = $refundService->getRefundRequest(Ulid::fromString($request->attributes->getString('id')));
            $refundService->approve($refundRequest);
        } catch (OrderException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $response = new JsonResponse();
        $response->setData(['refund_request' => json_decode($serializerService->serialize($refundRequest))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/refund-requests/{id}/reject',
        name: 'api_admin_refund_reject',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(
        Request $request,
        RefundService $refundService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $refundRequest = $refundService->getRefundRequest(Ulid::fromString($request->attributes->getString('id')));
            $refundService->reject($refundRequest);
        } catch (OrderException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $response = new JsonResponse();
        $response->setData(['refund_request' => json_decode($serializerService->serialize($refundRequest))]);

        return $response;
    }
}
