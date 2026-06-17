<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exceptions\JsonExceptionResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthenticationExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => 'onKernelException',
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
            $statusCode = Response::HTTP_UNAUTHORIZED;
        } elseif ($exception instanceof AccessDeniedHttpException || $exception instanceof AccessDeniedException) {
            $statusCode = Response::HTTP_FORBIDDEN;
        } else {
            return;
        }

        $event->setResponse(new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            'Unauthorized',
            $statusCode,
        ));
    }
}
