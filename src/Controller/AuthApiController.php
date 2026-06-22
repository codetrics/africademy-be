<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserLogType;
use App\Entity\UserProfile;
use App\Enum\AccountType;
use App\Exceptions\JsonExceptionResponse;
use App\Service\Helper\Tools;
use App\Service\RefreshTokenService;
use App\Service\RegistrationService;
use App\Service\SerializerService;
use App\Service\UserLogService;
use App\Service\VerificationService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthApiController extends AbstractController
{
    #[Route(
        '/api/{version}/public/auth/register',
        name: 'api_auth_register',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function register(
        Request $request,
        RegistrationService $registrationService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
        RateLimiterFactoryInterface $registrationLimiter,
        UserLogService $userLogService,
        VerificationService $verificationService,
        LoggerInterface $logger,
    ): JsonResponse {
        if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many registration attempts. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            Tools::checkExpectedKeys(['email', 'password', 'first_name', 'last_name', 'account_type'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $accountType = AccountType::tryFrom((string) $data['account_type']);
        if (is_null($accountType)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'Invalid account_type. Allowed values are "student" or "facilitator".',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        foreach ($validator->validate((string) $data['password'], Tools::passwordConstraints()) as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $profile = new UserProfile();
        $profile->setFirstName((string) $data['first_name']);
        $profile->setLastName((string) $data['last_name']);

        $user = new User();
        $user->setEmail((string) $data['email']);
        $user->setProfile($profile);

        $violations = $validator->validate($user);
        foreach ($violations as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $registrationService->register($user, (string) $data['password'], $accountType);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $verificationService->requestEmailVerification($user);
        } catch (Exception $exception) {
            $logger->error(sprintf('Failed to send verification email for %s: %s', $user->getEmail(), $exception->getMessage()));
        }

        $userLogService->log(
            UserLogType::REGISTER,
            'User registered',
            $user->getEmail(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        $userJSON = $serializerService->serialize($user);

        $response = new JsonResponse();
        $response->setData(['user' => json_decode($userJSON)]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/auth/logout',
        name: 'api_auth_logout',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function logout(
        Request $request,
        RefreshTokenService $refreshTokenService,
        UserLogService $userLogService,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $exception = $this->createAccessDeniedException();
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_UNAUTHORIZED,
                $exception->getMessage(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $refreshToken = array_key_exists('refresh_token', $data) ? (string) $data['refresh_token'] : '';
        $allDevices = ($data['all'] ?? false) === true;

        // A specific refresh token ends just that device; omitting it (or all=true)
        // ends every session. The access token itself remains valid until it expires.
        if (!$allDevices && $refreshToken !== '') {
            $refreshTokenService->revokeForUser($user, $refreshToken);
        } else {
            $refreshTokenService->revokeAllForUser($user);
        }

        $userLogService->log(
            UserLogType::LOGOUT,
            'User logged out',
            $user->getUserIdentifier(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
