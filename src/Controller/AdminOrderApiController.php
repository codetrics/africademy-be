<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Service\AdminOrderService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use DateTime;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class AdminOrderApiController extends AbstractController
{
    private const array TYPES = ['course', 'bundle'];

    #[Route(
        '/api/{version}/admin/orders',
        name: 'api_admin_order_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        AdminOrderService $adminOrderService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $statusValue = $request->query->getString('status');
        $status = $statusValue === '' ? null : OrderStatus::tryFrom($statusValue);
        if ($statusValue !== '' && is_null($status)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid status filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $typeValue = $request->query->getString('type');
        if ($typeValue !== '' && !in_array($typeValue, self::TYPES, true)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid type filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $type = $typeValue === '' ? null : $typeValue;

        $fromTimestamp = $request->query->getInt('from');
        $from = $fromTimestamp > 0 ? new DateTime()->setTimestamp($fromTimestamp) : null;
        $toTimestamp = $request->query->getInt('to');
        $to = $toTimestamp > 0 ? new DateTime()->setTimestamp($toTimestamp) : null;

        $pagination = $paginator->paginate(
            $adminOrderService->ordersQueryBuilder($status, $request->query->getString('q'), $type, $from, $to),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $orders = [];
        foreach ($pagination->getItems() as $order) {
            $payload = json_decode($serializerService->serialize($order), true);
            $payload['buyer'] = $adminOrderService->buyerSummary($order->getUser());
            $orders[] = $payload;
        }

        $response = new JsonResponse();
        $response->setData([
            'orders' => $orders,
            'counts' => $adminOrderService->countByStatus(),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/orders/{id}',
        name: 'api_admin_order_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function show(
        Request $request,
        AdminOrderService $adminOrderService,
        SerializerService $serializerService,
    ): JsonResponse {
        $order = $adminOrderService->getOrder(Ulid::fromString($request->attributes->getString('id')));
        if (!$order instanceof Order) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Order not found.', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($serializerService->serialize($order), true);
        $payload['buyer'] = $adminOrderService->buyerSummary($order->getUser());
        $payload['pf_payment_id'] = $order->getPfPaymentId();
        $payload['coupon'] = $order->getCoupon()?->getCode();

        $refundRequest = $adminOrderService->refundFor($order);
        $payload['refund_request'] = is_null($refundRequest)
            ? null
            : json_decode($serializerService->serialize($refundRequest), true);

        $response = new JsonResponse();
        $response->setData(['order' => $payload]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/orders/{id}/cancel',
        name: 'api_admin_order_cancel',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(
        Request $request,
        AdminOrderService $adminOrderService,
        SerializerService $serializerService,
    ): JsonResponse {
        $order = $adminOrderService->getOrder(Ulid::fromString($request->attributes->getString('id')));
        if (!$order instanceof Order) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Order not found.', Response::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() !== OrderStatus::Pending) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_CONFLICT, 'Only pending orders can be cancelled.', Response::HTTP_CONFLICT);
        }

        $adminOrderService->cancelPending($order);

        $response = new JsonResponse();
        $response->setData(['order' => json_decode($serializerService->serialize($order))]);

        return $response;
    }
}
