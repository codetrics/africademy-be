<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Helper\Tools;
use App\Entity\UserLogType;
use App\Exceptions\CouponException;
use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\OrderException;
use App\Service\OrderService;
use App\Service\PayFastService;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use App\Service\UserLogService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class OrderApiController extends AbstractController
{
    #[Route(
        '/api/{version}/students/courses/{id}/purchase',
        name: 'api_order_purchase',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function purchase(
        Request $request,
        OrderService $orderService,
        PayFastService $payFastService,
        SerializerService $serializerService,
        UserLogService $userLogService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $order = $orderService->createCoursePurchase($user, Ulid::fromString($request->attributes->getString('id')), $this->couponCode($request));
        } catch (OrderException | CouponException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $userLogService->log(
            UserLogType::PURCHASE_INITIATED,
            'Course purchase initiated',
            $user->getEmail(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
            ['order' => (string) $order->getPublicId()],
        );

        $response = new JsonResponse();
        $response->setData([
            'order' => json_decode($serializerService->serialize($order)),
            'payfast' => $payFastService->buildCheckout($order),
        ]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/students/bundles/{id}/purchase',
        name: 'api_order_bundle_purchase',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function purchaseBundle(
        Request $request,
        OrderService $orderService,
        PayFastService $payFastService,
        SerializerService $serializerService,
        UserLogService $userLogService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $order = $orderService->createBundlePurchase($user, Ulid::fromString($request->attributes->getString('id')), $this->couponCode($request));
        } catch (OrderException | CouponException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $userLogService->log(
            UserLogType::PURCHASE_INITIATED,
            'Bundle purchase initiated',
            $user->getEmail(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
            ['order' => (string) $order->getPublicId()],
        );

        $response = new JsonResponse();
        $response->setData([
            'order' => json_decode($serializerService->serialize($order)),
            'payfast' => $payFastService->buildCheckout($order),
        ]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/students/orders',
        name: 'api_order_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        OrderService $orderService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $pagination = $paginator->paginate(
            $orderService->createUserOrdersQueryBuilder($user),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 10)),
        );

        $response = new JsonResponse();
        $response->setData([
            'orders' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/orders/{id}',
        name: 'api_order_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        OrderService $orderService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $order = $orderService->getUserOrder($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (OrderException $exception) {
            return $this->mapOrderException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['order' => json_decode($serializerService->serialize($order))]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/orders/{id}/refund-request',
        name: 'api_order_refund_request',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function refundRequest(
        Request $request,
        OrderService $orderService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) && array_key_exists('reason', $data) && !is_null($data['reason'])
            ? (string) $data['reason']
            : null;

        try {
            $refundRequest = $orderService->requestRefund($user, Ulid::fromString($request->attributes->getString('id')), $reason);
        } catch (OrderException $exception) {
            return $this->mapOrderException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['refund_request' => json_decode($serializerService->serialize($refundRequest))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    private function couponCode(Request $request): ?string
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) && array_key_exists('coupon_code', $data) && !is_null($data['coupon_code'])
            ? (string) $data['coupon_code']
            : null;
    }

    private function narrowStudent(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function mapOrderException(OrderException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse(
            $exception->getErrorType(),
            $exception->getMessage(),
            $exception->getStatusCode(),
        );
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
