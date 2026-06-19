<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exceptions\JsonExceptionResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Normalises every exception raised on an /api request into the JSON error
 * envelope so the API never leaks an HTML error page (404, 500, …). Non-API
 * paths — including the Swagger UI — are left to Symfony's default handling.
 */
class APIExceptionsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => ['onKernelException', 50],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof AuthenticationException) {
            $event->setResponse($this->envelope(JsonExceptionResponse::ERROR_UNAUTHORIZED, 'Authentication required.', Response::HTTP_UNAUTHORIZED));

            return;
        }

        if ($exception instanceof AccessDeniedHttpException || $exception instanceof AccessDeniedException) {
            // Anonymous requests are an authentication problem (401); an
            // authenticated user lacking the role is a forbidden one (403).
            $token = $this->tokenStorage->getToken();

            if ($token === null || $token->getUser() === null) {
                $event->setResponse($this->envelope(JsonExceptionResponse::ERROR_UNAUTHORIZED, 'Authentication required.', Response::HTTP_UNAUTHORIZED));
            } else {
                $event->setResponse($this->envelope(JsonExceptionResponse::ERROR_UNAUTHORIZED, 'Access denied.', Response::HTTP_FORBIDDEN));
            }

            return;
        }

        if ($exception instanceof NotFoundHttpException) {
            $event->setResponse($this->envelope(JsonExceptionResponse::ERROR_NOT_FOUND, 'The requested resource was not found.', Response::HTTP_NOT_FOUND));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : (Response::$statusTexts[$statusCode] ?? 'Request failed.');
            $event->setResponse($this->envelope($this->errorTypeForStatus($statusCode), $message, $statusCode));

            return;
        }

        $this->logger->error('Unhandled API exception', [
            'exception' => $exception,
            'path' => $request->getPathInfo(),
        ]);

        $event->setResponse($this->envelope(
            JsonExceptionResponse::ERROR_INTERNAL_SERVER_ERROR,
            'An unexpected error occurred. Please try again later.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ));
    }

    private function envelope(string $errorType, string $message, int $statusCode): JsonExceptionResponse
    {
        return new JsonExceptionResponse($errorType, $message, $statusCode);
    }

    private function errorTypeForStatus(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN => JsonExceptionResponse::ERROR_UNAUTHORIZED,
            Response::HTTP_NOT_FOUND => JsonExceptionResponse::ERROR_NOT_FOUND,
            Response::HTTP_CONFLICT => JsonExceptionResponse::ERROR_CONFLICT,
            Response::HTTP_UNPROCESSABLE_ENTITY => JsonExceptionResponse::ERROR_VALIDATION,
            Response::HTTP_TOO_MANY_REQUESTS => JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
            default => JsonExceptionResponse::ERROR_INVALID_REQUEST,
        };
    }
}
