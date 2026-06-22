<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Enrollment;
use App\Service\Helper\Tools;
use App\Entity\User;
use App\Exceptions\EnrollmentException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\EnrollmentService;
use App\Service\ProgressService;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class EnrollmentApiController extends AbstractController
{
    #[Route(
        '/api/{version}/students/courses/{id}/enroll',
        name: 'api_enrollment_create',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function enroll(
        Request $request,
        EnrollmentService $enrollmentService,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $enrollment = $enrollmentService->enroll($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['enrollment' => $this->enrollmentPayload($enrollment, $progressService, $serializerService)]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/students/enrollments',
        name: 'api_enrollment_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        EnrollmentService $enrollmentService,
        ProgressService $progressService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $pagination = $paginator->paginate(
            $enrollmentService->createStudentEnrollmentsQueryBuilder($user),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 10)),
        );

        $enrollments = [];
        foreach ($pagination->getItems() as $enrollment) {
            $enrollments[] = $this->enrollmentPayload($enrollment, $progressService, $serializerService);
        }

        $response = new JsonResponse();
        $response->setData([
            'enrollments' => $enrollments,
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/enrollments/{id}',
        name: 'api_enrollment_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        EnrollmentService $enrollmentService,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $enrollment = $enrollmentService->getStudentEnrollment($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['enrollment' => $this->enrollmentPayload($enrollment, $progressService, $serializerService)]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/enrollments/{id}',
        name: 'api_enrollment_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function unenroll(
        Request $request,
        EnrollmentService $enrollmentService,
    ): JsonResponse {
        $user = $this->narrowStudent();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $enrollment = $enrollmentService->getStudentEnrollment($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (EnrollmentException $exception) {
            return $this->mapEnrollmentException($exception);
        }

        $enrollmentService->unenroll($enrollment);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function narrowStudent(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * Serialized enrollment with its computed progress attached under "progress".
     */
    private function enrollmentPayload(
        Enrollment $enrollment,
        ProgressService $progressService,
        SerializerService $serializerService,
    ): mixed {
        $payload = json_decode($serializerService->serialize($enrollment));
        $payload->progress = json_decode($serializerService->serialize($progressService->buildProgress($enrollment)));

        return $payload;
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
