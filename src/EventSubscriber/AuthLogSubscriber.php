<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\UserLogType;
use App\Service\UserLogService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Records login successes/failures to the user log. Hooks lexik's
 * authentication events, which fire only on token issuance (login/refresh),
 * not on every stateless per-request JWT check.
 */
class AuthLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserLogService $userLogService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $username = $user instanceof UserInterface ? $user->getUserIdentifier() : null;

        [$userAgent, $ipAddress] = $this->requestMeta();
        $this->userLogService->log(UserLogType::LOGIN, 'User logged in', $username, $userAgent, $ipAddress);
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        [$userAgent, $ipAddress] = $this->requestMeta();
        $this->userLogService->log(
            UserLogType::LOGIN_FAILED,
            'Login failed',
            $this->attemptedUsername(),
            $userAgent,
            $ipAddress,
            ['reason' => $event->getException()->getMessage()],
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function requestMeta(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (is_null($request)) {
            return [null, null];
        }

        return [$request->headers->get('User-Agent'), $request->getClientIp()];
    }

    private function attemptedUsername(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (is_null($request)) {
            return null;
        }

        $data = json_decode((string) $request->getContent(), true);

        return is_array($data) && array_key_exists('email', $data) ? (string) $data['email'] : null;
    }
}
