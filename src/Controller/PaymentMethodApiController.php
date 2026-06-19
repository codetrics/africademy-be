<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\PaymentMethodException;
use App\Service\PaymentMethodService;
use App\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class PaymentMethodApiController extends AbstractController
{
    #[Route(
        '/api/{version}/payment-methods/setup',
        name: 'api_payment_method_setup',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function setup(
        PaymentMethodService $paymentMethodService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $response = new JsonResponse();
        $response->setData(['payfast' => $paymentMethodService->createSetupCheckout($user)]);

        return $response;
    }

    #[Route(
        '/api/{version}/payment-methods',
        name: 'api_payment_method_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        PaymentMethodService $paymentMethodService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $response = new JsonResponse();
        $response->setData(['payment_methods' => json_decode($serializerService->serialize($paymentMethodService->list($user)))]);

        return $response;
    }

    #[Route(
        '/api/{version}/payment-methods/{id}/default',
        name: 'api_payment_method_default',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function setDefault(
        Request $request,
        PaymentMethodService $paymentMethodService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $paymentMethod = $paymentMethodService->setDefault($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (PaymentMethodException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $response = new JsonResponse();
        $response->setData(['payment_method' => json_decode($serializerService->serialize($paymentMethod))]);

        return $response;
    }

    #[Route(
        '/api/{version}/payment-methods/{id}',
        name: 'api_payment_method_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        PaymentMethodService $paymentMethodService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $paymentMethodService->delete($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (PaymentMethodException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function narrowStudent(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
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
