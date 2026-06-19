<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\NewsletterException;
use App\Service\Helper\Tools;
use App\Service\NewsletterService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NewsletterApiController extends AbstractController
{
    #[Route(
        '/api/{version}/newsletter/subscribe',
        name: 'api_newsletter_subscribe',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function subscribe(
        Request $request,
        NewsletterService $newsletterService,
        ValidatorInterface $validator,
        RateLimiterFactoryInterface $newsletterLimiter,
    ): JsonResponse {
        if (!$newsletterLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many attempts. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            Tools::checkExpectedKeys(['email'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $email = (string) $data['email'];
        $violations = $validator->validate($email, new Assert\Email(message: 'Please provide a valid email address.'));
        foreach ($violations as $violation) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $violation->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $newsletterService->subscribe($email);
        } catch (NewsletterException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        return new JsonResponse(['message' => 'Subscribed. Check your inbox to confirm.'], Response::HTTP_CREATED);
    }

    #[Route(
        '/api/{version}/newsletter/unsubscribe',
        name: 'api_newsletter_unsubscribe',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function unsubscribe(
        Request $request,
        NewsletterService $newsletterService,
    ): JsonResponse {
        $token = $request->query->getString('token');
        if ($token === '') {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'Missing unsubscribe token.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $newsletterService->unsubscribe($token);
        } catch (NewsletterException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        return new JsonResponse(['message' => 'You have been unsubscribed.']);
    }
}
