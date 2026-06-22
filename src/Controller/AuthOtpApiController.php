<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserLogType;
use App\Exceptions\JsonExceptionResponse;
use App\Service\Helper\Tools;
use App\Service\OtpLoginService;
use App\Service\UserLogService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthOtpApiController extends AbstractController
{
    #[Route(
        '/api/{version}/auth/login/otp/verify',
        name: 'api_auth_login_otp_verify',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function verify(
        Request $request,
        OtpLoginService $otpLoginService,
        UserLogService $userLogService,
        RateLimiterFactoryInterface $loginOtpVerifyLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $preAuthToken = (string) ($data['pre_auth_token'] ?? '');

        // Scope the brute-force limiter per email+IP (not IP alone) so attempts on
        // one account cannot consume another account's budget.
        $limiterKey = ($otpLoginService->peekEmail($preAuthToken) ?? 'anonymous') . '|' . $request->getClientIp();
        if (!$loginOtpVerifyLimiter->create($limiterKey)->consume()->isAccepted()) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many attempts. Please request a new code and try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            Tools::checkExpectedKeys(['pre_auth_token', 'code'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $otpLoginService->completeOtp($preAuthToken, (string) $data['code']);

        if (is_null($result)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'The one-time code is invalid or has expired. Please try again or request a new code.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var User $user */
        $user = $result['user'];
        $userLogService->log(
            UserLogType::LOGIN,
            'User logged in',
            $user->getUserIdentifier(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        return new JsonResponse($result['tokens']);
    }

    #[Route(
        '/api/{version}/auth/login/otp/request',
        name: 'api_auth_login_otp_request',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function request(
        Request $request,
        OtpLoginService $otpLoginService,
        RateLimiterFactoryInterface $loginOtpRequestLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $preAuthToken = (string) ($data['pre_auth_token'] ?? '');

        // Scope the resend limiter per email+IP so one account's resends cannot
        // exhaust another account's budget (and to throttle per-victim flooding).
        $limiterKey = ($otpLoginService->peekEmail($preAuthToken) ?? 'anonymous') . '|' . $request->getClientIp();
        if (!$loginOtpRequestLimiter->create($limiterKey)->consume()->isAccepted()) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many code requests. Please wait a few minutes and try again.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            Tools::checkExpectedKeys(['pre_auth_token'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!$otpLoginService->resendOtp($preAuthToken)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_UNAUTHORIZED,
                'Pre-authentication token is invalid or has expired. Please sign in again.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
