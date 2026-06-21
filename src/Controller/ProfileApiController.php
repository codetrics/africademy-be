<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserLogType;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\UserProfileRepository;
use App\Repository\UserRepository;
use App\Service\AvatarUploadService;
use App\Service\Helper\Tools;
use App\Service\RefreshTokenService;
use App\Service\SerializerService;
use App\Service\UserLogService;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileApiController extends AbstractController
{
    private const array UPDATABLE_FIELDS = ['first_name', 'last_name', 'bio', 'phone', 'locale', 'timezone'];

    #[Route(
        '/api/{version}/profile',
        name: 'api_profile_get',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function getProfile(
        SerializerService $serializerService,
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

        $profileJSON = $serializerService->serialize($user->getProfile());

        $response = new JsonResponse();
        $response->setData(['profile' => json_decode($profileJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/profile',
        name: 'api_profile_patch',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function patchProfile(
        Request $request,
        UserProfileRepository $userProfileRepository,
        SerializerService $serializerService,
        ValidatorInterface $validator,
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
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $profile = $user->getProfile();

        foreach (self::UPDATABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            match ($field) {
                'first_name' => $profile->setFirstName((string) $data['first_name']),
                'last_name' => $profile->setLastName((string) $data['last_name']),
                'bio' => $profile->setBio(is_null($data['bio']) ? null : (string) $data['bio']),
                'phone' => $profile->setPhone(is_null($data['phone']) ? null : (string) $data['phone']),
                'locale' => $profile->setLocale((string) $data['locale']),
                'timezone' => $profile->setTimezone((string) $data['timezone']),
            };
        }

        $violations = $validator->validate($profile);
        foreach ($violations as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $profile->setUpdatedAt(new DateTime());
        $userProfileRepository->save($profile, true);

        $profileJSON = $serializerService->serialize($profile);

        $response = new JsonResponse();
        $response->setData(['profile' => json_decode($profileJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/profile/password',
        name: 'api_profile_password_change',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function changePassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        UserLogService $userLogService,
        RefreshTokenService $refreshTokenService,
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
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            Tools::checkExpectedKeys(['current_password', 'new_password'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!$passwordHasher->isPasswordValid($user, (string) $data['current_password'])) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'Current password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $newPassword = (string) $data['new_password'];

        foreach ($validator->validate($newPassword, Tools::passwordConstraints()) as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($passwordHasher->isPasswordValid($user, $newPassword)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'New password must be different from the current password.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        // Force re-OTP on the next login and end other sessions after a credential change.
        $user->setLastOtpAt(null);
        $userRepository->save($user, true);
        $refreshTokenService->revokeAllForUser($user);

        $userLogService->log(
            UserLogType::PASSWORD_CHANGE,
            'Password changed',
            $user->getUserIdentifier(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/profile/avatar',
        name: 'api_profile_avatar_post',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function postAvatar(
        Request $request,
        AvatarUploadService $avatarUploadService,
        UserProfileRepository $userProfileRepository,
        SerializerService $serializerService,
        ValidatorInterface $validator,
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

        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                'No avatar file was uploaded.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $violations = $validator->validate($file, new Assert\Image(
            maxSize: '2M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Please upload a valid image (JPEG, PNG or WebP).',
        ));
        foreach ($violations as $violation) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $profile = $user->getProfile();

        try {
            $storedPath = $avatarUploadService->store($file, $profile->getAvatarPath());
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $profile->setAvatarPath($storedPath);
        $profile->setUpdatedAt(new DateTime());
        $userProfileRepository->save($profile, true);

        $profileJSON = $serializerService->serialize($profile);

        $response = new JsonResponse();
        $response->setData(['profile' => json_decode($profileJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/profile/avatar',
        name: 'api_profile_avatar_delete',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function deleteAvatar(
        AvatarUploadService $avatarUploadService,
        UserProfileRepository $userProfileRepository,
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

        $profile = $user->getProfile();
        $avatarPath = $profile->getAvatarPath();

        if (is_null($avatarPath)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'No avatar to delete.',
                Response::HTTP_NOT_FOUND,
            );
        }

        $avatarUploadService->delete($avatarPath);
        $profile->setAvatarPath(null);
        $profile->setUpdatedAt(new DateTime());
        $userProfileRepository->save($profile, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/profile/avatar',
        name: 'api_profile_avatar_get',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json', 'version' => 'v1'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function getAvatar(
        AvatarUploadService $avatarUploadService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $exception = $this->createAccessDeniedException();
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_UNAUTHORIZED,
                $exception->getMessage(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $avatarPath = $user->getProfile()->getAvatarPath();

        if (is_null($avatarPath)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'No avatar set.',
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $absolutePath = $avatarUploadService->getAbsolutePath($avatarPath);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                $exception->getMessage(),
                Response::HTTP_NOT_FOUND,
            );
        }

        return new BinaryFileResponse($absolutePath);
    }
}
