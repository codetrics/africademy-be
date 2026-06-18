<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coupon;
use App\Enum\DiscountType;
use App\Exceptions\CouponException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\CouponService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use DateTime;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class AdminCouponApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/coupons',
        name: 'api_admin_coupon_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        CouponService $couponService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $pagination = $paginator->paginate(
            $couponService->listQueryBuilder(),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 10),
        );

        $response = new JsonResponse();
        $response->setData([
            'coupons' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/coupons',
        name: 'api_admin_coupon_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        CouponService $couponService,
        SerializerService $serializerService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            Tools::checkExpectedKeys(['code', 'discount_type', 'discount_value'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $code = strtoupper(trim((string) $data['code']));
        if ($code === '' || $couponService->codeExists($code)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_CONFLICT, 'A coupon with this code already exists.', Response::HTTP_CONFLICT);
        }

        $discountType = DiscountType::tryFrom((string) $data['discount_type']);
        if (is_null($discountType)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid discount type.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $coupon = new Coupon();
        $coupon->setCode($code);
        $coupon->setDiscountType($discountType);

        $error = $this->applyWritableFields($coupon, $data, true);
        if (!is_null($error)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $error, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $couponService->save($coupon);

        $response = new JsonResponse();
        $response->setData(['coupon' => json_decode($serializerService->serialize($coupon))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/coupons/{id}',
        name: 'api_admin_coupon_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Request $request,
        CouponService $couponService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $coupon = $couponService->getByPublicId(Ulid::fromString($request->attributes->getString('id')));
        } catch (CouponException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        $error = $this->applyWritableFields($coupon, $data, false);
        if (!is_null($error)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $error, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $couponService->save($coupon);

        $response = new JsonResponse();
        $response->setData(['coupon' => json_decode($serializerService->serialize($coupon))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/coupons/{id}',
        name: 'api_admin_coupon_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        CouponService $couponService,
    ): JsonResponse {
        try {
            $coupon = $couponService->getByPublicId(Ulid::fromString($request->attributes->getString('id')));
        } catch (CouponException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $couponService->delete($coupon);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyWritableFields(Coupon $coupon, array $data, bool $isCreate): ?string
    {
        if ($isCreate || array_key_exists('discount_value', $data)) {
            $value = (int) ($data['discount_value'] ?? 0);
            if ($value <= 0 || ($coupon->getDiscountType() === DiscountType::Percent && $value > 100)) {
                return 'Invalid discount value.';
            }
            $coupon->setDiscountValue($value);
        }

        if (array_key_exists('max_redemptions', $data)) {
            $coupon->setMaxRedemptions(is_null($data['max_redemptions']) ? null : max(1, (int) $data['max_redemptions']));
        }

        if (array_key_exists('min_amount_cents', $data)) {
            $coupon->setMinAmountCents(is_null($data['min_amount_cents']) ? null : max(0, (int) $data['min_amount_cents']));
        }

        if (array_key_exists('expires_at', $data)) {
            $coupon->setExpiresAt(is_null($data['expires_at']) ? null : new DateTime('@' . (int) $data['expires_at']));
        }

        if (array_key_exists('is_active', $data)) {
            $coupon->setActive((bool) $data['is_active']);
        }

        return null;
    }
}
