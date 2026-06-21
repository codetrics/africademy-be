<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserLogType;
use App\Entity\UserProfile;
use App\Enum\AccountType;
use App\Exceptions\JsonExceptionResponse;
use App\Service\Helper\Tools;
use App\Service\RegistrationService;
use App\Service\SerializerService;
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

final class AuthApiController extends AbstractController
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

    #[Route(
        '/api/{version}/auth/register',
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
                'Invalid account_type. Allowed values are "student" or "teacher".',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (strlen((string) $data['password']) < self::MINIMUM_PASSWORD_LENGTH) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                sprintf('Password must be at least %d characters long.', self::MINIMUM_PASSWORD_LENGTH),
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

        $verificationService->requestEmailVerification($user);

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
}
