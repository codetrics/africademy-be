<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Helper\Tools;
use App\Enum\UserStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Service\AdminDirectoryService;
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

final class AdminStudentApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/students',
        name: 'api_admin_student_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        AdminDirectoryService $adminDirectoryService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $statusValue = $request->query->getString('status');
        $status = $statusValue === '' ? null : UserStatus::tryFrom($statusValue);

        if ($statusValue !== '' && is_null($status)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid status filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pagination = $paginator->paginate(
            $adminDirectoryService->studentsQueryBuilder($request->query->getString('q'), $status),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'students' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/students/{id}',
        name: 'api_admin_student_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function show(
        Request $request,
        AdminDirectoryService $adminDirectoryService,
        SerializerService $serializerService,
    ): JsonResponse {
        $student = $adminDirectoryService->findStudent(Ulid::fromString($request->attributes->getString('id')));

        if (!$student instanceof User) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Student not found.', Response::HTTP_NOT_FOUND);
        }

        $response = new JsonResponse();
        $response->setData([
            'student' => json_decode($serializerService->serialize($student)),
            'summary' => $adminDirectoryService->studentSummary($student),
            'recent_activity' => json_decode($serializerService->serialize($adminDirectoryService->recentActivity($student))),
        ]);

        return $response;
    }
}
