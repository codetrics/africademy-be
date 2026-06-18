<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\EnrollmentException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\ProgressService;
use App\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class ProgressApiController extends AbstractController
{
    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}/complete',
        name: 'api_progress_complete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function complete(
        Request $request,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $progress = $progressService->markComplete(
                $user,
                Ulid::fromString($request->attributes->getString('courseId')),
                Ulid::fromString($request->attributes->getString('id')),
            );
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['progress' => json_decode($serializerService->serialize($progress))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}/complete',
        name: 'api_progress_uncomplete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function uncomplete(
        Request $request,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $progress = $progressService->unmarkComplete(
                $user,
                Ulid::fromString($request->attributes->getString('courseId')),
                Ulid::fromString($request->attributes->getString('id')),
            );
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['progress' => json_decode($serializerService->serialize($progress))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}/learn',
        name: 'api_progress_learn',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function learn(
        Request $request,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $view = $progressService->getLearnView($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $lessons = [];
        foreach ($view['lessons'] as $entry) {
            $lesson = json_decode($serializerService->serialize($entry['lesson']));
            $lesson->state = $entry['state'];
            $lessons[] = $lesson;
        }

        $response = new JsonResponse();
        $response->setData([
            'progress' => json_decode($serializerService->serialize($view['progress'])),
            'lessons' => $lessons,
        ]);

        return $response;
    }

    private function narrowStudent(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function mapEnrollmentException(EnrollmentException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse(
            $exception->getErrorType(),
            $exception->getMessage(),
            $exception->getStatusCode(),
        );
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
