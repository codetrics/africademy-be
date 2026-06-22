<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Course;
use App\Enum\CourseLevel;
use App\Enum\CourseStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\CategoryRepository;
use App\Repository\CourseRepository;
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
 * Authenticated student catalogue: published courses only. Facilitators manage
 * their own courses under /facilitator/courses; admins under /admin/courses;
 * anonymous browsing is under /public/courses.
 */
final class CourseApiController extends AbstractController
{
    #[Route(
        '/api/{version}/courses',
        name: 'api_course_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        CourseRepository $courseRepository,
        CategoryRepository $categoryRepository,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $page = $request->query->getInt('page', 1);
        $limit = Tools::clampLimit($request->query->getInt('limit', 10));
        $categorySlug = $request->query->getString('category');
        $levelValue = $request->query->getString('level');
        $search = $request->query->getString('q');

        $category = $categorySlug !== '' ? $categoryRepository->findOneBySlug($categorySlug) : null;
        $level = $levelValue !== '' ? CourseLevel::tryFrom($levelValue) : null;

        // A filter that resolves to nothing yields an empty page rather than an unfiltered one.
        if (($categorySlug !== '' && !$category instanceof Category) || ($levelValue !== '' && is_null($level))) {
            return $this->paginatedCoursesResponse([], 0, $page, $limit, $serializerService);
        }

        $queryBuilder = $courseRepository->createCatalogQueryBuilder($category, $level, $search, null);
        $pagination = $paginator->paginate($queryBuilder, $page, $limit);

        $response = new JsonResponse();
        $response->setData([
            'courses' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}',
        name: 'api_course_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        CourseRepository $courseRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));

        if (!$course instanceof Course || $course->getStatus() !== CourseStatus::Published) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'Course not found',
                Response::HTTP_NOT_FOUND,
            );
        }

        $response = new JsonResponse();
        $response->setData(['course' => json_decode($serializerService->serialize($course))]);

        return $response;
    }

    /**
     * @param Course[] $courses
     */
    private function paginatedCoursesResponse(
        array $courses,
        int $totalItems,
        int $page,
        int $limit,
        SerializerService $serializerService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData([
            'courses' => json_decode($serializerService->serialize($courses)),
            'pagination' => [
                'current_page' => $page,
                'items_per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => 0,
            ],
        ]);

        return $response;
    }
}
