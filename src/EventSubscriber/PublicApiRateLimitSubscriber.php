<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exceptions\JsonExceptionResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Applies a baseline per-IP rate limit to anonymous /api requests — i.e. every
 * public endpoint (auth, blog reads, newsletter, certificate verification).
 * Authenticated (Bearer) requests are skipped here; they are bounded per-user by
 * their own controllers. Stricter per-feature limiters still stack on top.
 */
class PublicApiRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Authenticated requests carry a bearer token and are limited per-user elsewhere.
        if (str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ')) {
            return;
        }

        if (!$this->publicApiLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $event->setResponse(new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many requests. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            ));
        }
    }
}
