<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\SubscriptionException;
use App\Service\Helper\Tools;
use App\Service\SerializerService;
use App\Service\SubscriptionService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class SubscriptionApiController extends AbstractController
{
    #[Route(
        '/api/{version}/plans',
        name: 'api_plan_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function plans(
        SubscriptionService $subscriptionService,
        SerializerService $serializerService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData(['plans' => json_decode($serializerService->serialize($subscriptionService->listPlans()))]);

        return $response;
    }

    #[Route(
        '/api/{version}/subscriptions',
        name: 'api_subscription_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function subscribe(
        Request $request,
        SubscriptionService $subscriptionService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            Tools::checkExpectedKeys(['plan_id', 'payment_method_id'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if (!Ulid::isValid((string) $data['plan_id']) || !Ulid::isValid((string) $data['payment_method_id'])) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'Invalid identifier.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $subscription = $subscriptionService->subscribe(
                $user,
                Ulid::fromString((string) $data['plan_id']),
                Ulid::fromString((string) $data['payment_method_id']),
            );
        } catch (SubscriptionException $exception) {
            return $this->mapSubscriptionException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['subscription' => json_decode($serializerService->serialize($subscription))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/subscription',
        name: 'api_subscription_current',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function current(
        SubscriptionService $subscriptionService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $subscription = $subscriptionService->getCurrent($user);
        if (!$subscription instanceof Subscription) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'No subscription found.', Response::HTTP_NOT_FOUND);
        }

        $response = new JsonResponse();
        $response->setData(['subscription' => json_decode($serializerService->serialize($subscription))]);

        return $response;
    }

    #[Route(
        '/api/{version}/subscriptions/{id}/cancel',
        name: 'api_subscription_cancel',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function cancel(
        Request $request,
        SubscriptionService $subscriptionService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $subscription = $subscriptionService->getUserSubscription($user, Ulid::fromString($request->attributes->getString('id')));
            $subscriptionService->cancel($subscription);
        } catch (SubscriptionException $exception) {
            return $this->mapSubscriptionException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['subscription' => json_decode($serializerService->serialize($subscription))]);

        return $response;
    }

    private function narrowStudent(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function mapSubscriptionException(SubscriptionException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(JsonExceptionResponse::ERROR_UNAUTHORIZED, $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
