<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Forces authenticated users to verify their email before interacting with the
 * API. Runs just after the firewall (priority 7) so the user is available, and
 * blocks every /api request with a distinct email_not_verified error except
 * PUBLIC_ACCESS routes (per security.yaml), the caller's own profile read, and
 * admins.
 */
class EmailVerificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessMapInterface $accessMap,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Public endpoints stay open regardless of verification. Reuse the firewall
        // access map so any PUBLIC_ACCESS route (auth, blog reads, newsletter,
        // certificate verification, …) is exempt without duplicating the list.
        [$attributes] = $this->accessMap->getPatterns($request);
        if (in_array(AuthenticatedVoter::PUBLIC_ACCESS, (array) $attributes, true)) {
            return;
        }

        // The caller may read their own profile to render the "verify" screen.
        if ($request->isMethod(Request::METHOD_GET) && preg_match('#^/api/v\d+/profile$#', $path) === 1) {
            return;
        }

        $user = $this->security->getUser();

        // No authenticated user (anonymous/public endpoint) — let the normal
        // security pipeline decide.
        if (!$user instanceof User) {
            return;
        }

        // Admins bypass the verification gate.
        if ($this->security->isGranted(User::ROLE_ADMIN)) {
            return;
        }

        if (!$user->isEmailVerified()) {
            $event->setResponse(new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_EMAIL_NOT_VERIFIED,
                'Please verify your email address to continue.',
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
