<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exceptions\JsonExceptionResponse;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Applies a baseline per-IP rate limit to anonymous /api requests — every public
 * endpoint (auth, blog reads, newsletter, certificate verification). Runs AFTER
 * the firewall so the decision is based on the actual authenticated user, not a
 * (spoofable) Authorization header: genuinely authenticated requests are skipped
 * (bounded per-user by their own controllers); everything else is capped here.
 * Stricter per-feature limiters still stack on top.
 */
class PublicApiRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Target('public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 6 — below the firewall (8) so the security token is populated.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 6],
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

        // Genuinely authenticated requests are bounded per-user elsewhere. A
        // spoofed/invalid token never reaches here authenticated — the firewall
        // rejects it — so anonymous traffic (incl. fake bearers that fall through)
        // is capped.
        if ($this->tokenStorage->getToken()?->getUser() instanceof UserInterface) {
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
