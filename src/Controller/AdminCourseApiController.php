<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\CategoryRepository;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use App\Service\CourseService;
use App\Service\Helper\Tools;
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

/**
 * Admin oversight of all facilitators' courses: view everything and moderate
 * (publish / unpublish / delete). Content editing remains with the facilitator
 * who owns the course.
 */
final class AdminCourseApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/courses',
        name: 'api_admin_course_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        CourseRepository $courseRepository,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $statusValue = $request->query->getString('status');
        $status = $statusValue === '' ? null : CourseStatus::tryFrom($statusValue);
        if ($statusValue !== '' && is_null($status)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid status filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $categorySlug = $request->query->getString('category');
        $category = $categorySlug !== '' ? $categoryRepository->findOneBySlug($categorySlug) : null;
        if ($categorySlug !== '' && !$category instanceof Category) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid category filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ownerId = $request->query->getString('owner');
        $owner = null;
        if ($ownerId !== '') {
            $owner = Ulid::isValid($ownerId) ? $userRepository->findOneByPublicId(Ulid::fromString($ownerId)) : null;
            if (!$owner instanceof User) {
                return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid owner filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $pagination = $paginator->paginate(
            $courseRepository->createManagementQueryBuilder($request->query->getString('q'), $status, $category, $owner),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'courses' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/courses/{id}',
        name: 'api_admin_course_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function get(
        Request $request,
        CourseRepository $courseRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found', Response::HTTP_NOT_FOUND);
        }

        $response = new JsonResponse();
        $response->setData(['course' => json_decode($serializerService->serialize($course))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/courses/{id}/publish',
        name: 'api_admin_course_publish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function publish(
        Request $request,
        CourseRepository $courseRepository,
        CourseService $courseService,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found', Response::HTTP_NOT_FOUND);
        }

        $courseService->publish($course);

        $response = new JsonResponse();
        $response->setData(['course' => json_decode($serializerService->serialize($course))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/courses/{id}/unpublish',
        name: 'api_admin_course_unpublish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function unpublish(
        Request $request,
        CourseRepository $courseRepository,
        CourseService $courseService,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found', Response::HTTP_NOT_FOUND);
        }

        $courseService->unpublish($course);

        $response = new JsonResponse();
        $response->setData(['course' => json_decode($serializerService->serialize($course))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/courses/{id}',
        name: 'api_admin_course_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        CourseRepository $courseRepository,
        CourseService $courseService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found', Response::HTTP_NOT_FOUND);
        }

        $courseService->delete($course);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
