<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserLogType;
use App\Service\OtpLoginService;
use App\Service\UserLogService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Runs after json_login verifies the password. Instead of returning an access
 * token directly, it starts an OTP session: trusted accounts get the real tokens,
 * everyone else gets a pre-auth token and an emailed one-time code.
 */
final class OtpLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly OtpLoginService $otpLoginService,
        private readonly UserLogService $userLogService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['otp_pending' => false]);
        }

        $payload = $this->otpLoginService->startSession($user);

        if ($payload['otp_pending'] === false) {
            $this->userLogService->log(
                UserLogType::LOGIN,
                'User logged in',
                $user->getUserIdentifier(),
                $request->headers->get('User-Agent'),
                $request->getClientIp(),
            );
        }

        return new JsonResponse($payload);
    }
}
