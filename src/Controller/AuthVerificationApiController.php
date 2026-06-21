<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserLogType;
use App\Exceptions\JsonExceptionResponse;
use App\Service\Helper\Tools;
use App\Service\UserLogService;
use App\Service\VerificationService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthVerificationApiController extends AbstractController
{

    #[Route(
        '/api/{version}/auth/verify-email/request',
        name: 'api_auth_verify_email_request',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function requestEmailVerification(
        Request $request,
        VerificationService $verificationService,
        UserLogService $userLogService,
        RateLimiterFactoryInterface $verificationLimiter,
    ): JsonResponse {
        $rateLimited = $this->enforceRateLimit($request, $verificationLimiter);
        if ($rateLimited instanceof JsonResponse) {
            return $rateLimited;
        }

        $data = $this->parseEmail($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $verificationService->requestEmailVerificationByEmail($data);
        $this->log($userLogService, UserLogType::EMAIL_VERIFICATION_REQUEST, 'Email verification requested', $data, $request);

        return new JsonResponse(['message' => 'If the email exists, a verification code has been sent.']);
    }

    #[Route(
        '/api/{version}/auth/verify-email',
        name: 'api_auth_verify_email',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function verifyEmail(
        Request $request,
        VerificationService $verificationService,
        UserLogService $userLogService,
        RateLimiterFactoryInterface $verificationLimiter,
    ): JsonResponse {
        $rateLimited = $this->enforceRateLimit($request, $verificationLimiter);
        if ($rateLimited instanceof JsonResponse) {
            return $rateLimited;
        }

        $data = $this->parsePayload($request, ['email', 'code']);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (!$verificationService->verifyEmail((string) $data['email'], (string) $data['code'])) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'The verification code is invalid or has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->log($userLogService, UserLogType::EMAIL_VERIFICATION, 'Email verified', (string) $data['email'], $request);

        return new JsonResponse(['message' => 'Email verified.']);
    }

    #[Route(
        '/api/{version}/auth/password/forgot',
        name: 'api_auth_password_forgot',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function forgotPassword(
        Request $request,
        VerificationService $verificationService,
        UserLogService $userLogService,
        RateLimiterFactoryInterface $verificationLimiter,
    ): JsonResponse {
        $rateLimited = $this->enforceRateLimit($request, $verificationLimiter);
        if ($rateLimited instanceof JsonResponse) {
            return $rateLimited;
        }

        $data = $this->parseEmail($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $verificationService->requestPasswordReset($data);
        $this->log($userLogService, UserLogType::PASSWORD_RESET_REQUEST, 'Password reset requested', $data, $request);

        return new JsonResponse(['message' => 'If the email exists, a reset code has been sent.']);
    }

    #[Route(
        '/api/{version}/auth/password/reset',
        name: 'api_auth_password_reset',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function resetPassword(
        Request $request,
        VerificationService $verificationService,
        UserLogService $userLogService,
        RateLimiterFactoryInterface $verificationLimiter,
        ValidatorInterface $validator,
    ): JsonResponse {
        $rateLimited = $this->enforceRateLimit($request, $verificationLimiter);
        if ($rateLimited instanceof JsonResponse) {
            return $rateLimited;
        }

        $data = $this->parsePayload($request, ['email', 'code', 'password']);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        foreach ($validator->validate((string) $data['password'], Tools::passwordConstraints()) as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$verificationService->resetPassword((string) $data['email'], (string) $data['code'], (string) $data['password'])) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'The reset code is invalid or has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->log($userLogService, UserLogType::PASSWORD_RESET, 'Password reset', (string) $data['email'], $request);

        return new JsonResponse(['message' => 'Password updated.']);
    }

    private function enforceRateLimit(Request $request, RateLimiterFactoryInterface $verificationLimiter): ?JsonResponse
    {
        if ($verificationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return null;
        }

        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
            'Too many attempts. Please try again later.',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    /**
     * @return string|JsonResponse the email string, or an error response
     */
    private function parseEmail(Request $request): string|JsonResponse
    {
        $data = $this->parsePayload($request, ['email']);

        return $data instanceof JsonResponse ? $data : (string) $data['email'];
    }

    /**
     * @param string[] $requiredKeys
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function parsePayload(Request $request, array $requiredKeys): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            Tools::checkExpectedKeys($requiredKeys, $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        return $data;
    }

    private function log(UserLogService $userLogService, string $typeSlug, string $message, string $username, Request $request): void
    {
        $userLogService->log($typeSlug, $message, $username, $request->headers->get('User-Agent'), $request->getClientIp());
    }
}
