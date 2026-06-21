<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Service\AdminDirectoryService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use App\Service\TeacherApprovalService;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class AdminTeacherApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/teachers',
        name: 'api_admin_teacher_list',
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
            $adminDirectoryService->teachersQueryBuilder($request->query->getString('q'), $status),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'teachers' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/teachers/{id}/approve',
        name: 'api_admin_teacher_approve',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(
        Request $request,
        AdminDirectoryService $adminDirectoryService,
        TeacherApprovalService $teacherApprovalService,
    ): JsonResponse {
        $teacher = $adminDirectoryService->findTeacher(Ulid::fromString($request->attributes->getString('id')));

        if (!$teacher instanceof User) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Teacher not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $teacherApprovalService->approve($teacher);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/admin/teachers/{id}/reject',
        name: 'api_admin_teacher_reject',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(
        Request $request,
        AdminDirectoryService $adminDirectoryService,
        TeacherApprovalService $teacherApprovalService,
    ): JsonResponse {
        $teacher = $adminDirectoryService->findTeacher(Ulid::fromString($request->attributes->getString('id')));

        if (!$teacher instanceof User) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Teacher not found.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $reason = is_array($data) && array_key_exists('reason', $data) ? (string) $data['reason'] : null;

        try {
            $teacherApprovalService->reject($teacher, $reason);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
