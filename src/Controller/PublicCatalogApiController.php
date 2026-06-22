<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bundle;
use App\Entity\Course;
use App\Enum\CourseStatus;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\BundleRepository;
use App\Repository\CategoryRepository;
use App\Repository\CourseRepository;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use App\Service\SubscriptionService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Anonymous, read-only catalogue: published courses and bundles, active
 * subscription plans, and categories. Everything is serialised with the JMS
 * "public" group so only allowlisted, non-sensitive fields are exposed.
 */
final class PublicCatalogApiController extends AbstractController
{
    private const array PUBLIC_GROUP = ['public'];

    #[Route(
        '/api/{version}/public/courses',
        name: 'api_public_course_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function courses(
        Request $request,
        CourseRepository $courseRepository,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $pagination = $paginator->paginate(
            $courseRepository->createCatalogQueryBuilder(null, null, $request->query->getString('q'), null),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'courses' => json_decode($serializerService->serialize($pagination->getItems(), 'json', self::PUBLIC_GROUP)),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/public/courses/{slug}',
        name: 'api_public_course_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'slug' => '[a-zA-Z0-9-]+'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function course(
        Request $request,
        CourseRepository $courseRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $identifier = $request->attributes->getString('slug');
        $course = Ulid::isValid($identifier)
            ? $courseRepository->findOneByPublicId(Ulid::fromString($identifier))
            : $courseRepository->findPublishedBySlug($identifier);

        // Only published courses are public, whether looked up by slug or ULID.
        if (!$course instanceof Course || $course->getStatus() !== CourseStatus::Published) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found.', Response::HTTP_NOT_FOUND);
        }

        $response = new JsonResponse();
        $response->setData(['course' => json_decode($serializerService->serialize($course, 'json', self::PUBLIC_GROUP))]);

        return $response;
    }

    #[Route(
        '/api/{version}/public/bundles',
        name: 'api_public_bundle_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function bundles(
        Request $request,
        BundleRepository $bundleRepository,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $pagination = $paginator->paginate(
            $bundleRepository->createCatalogQueryBuilder(null),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'bundles' => json_decode($serializerService->serialize($pagination->getItems(), 'json', self::PUBLIC_GROUP)),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/public/bundles/{slug}',
        name: 'api_public_bundle_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'slug' => '[a-zA-Z0-9-]+'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function bundle(
        Request $request,
        BundleRepository $bundleRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $bundle = $bundleRepository->findPublishedBySlug($request->attributes->getString('slug'));

        if (!$bundle instanceof Bundle) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Bundle not found.', Response::HTTP_NOT_FOUND);
        }

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle, 'json', self::PUBLIC_GROUP))]);

        return $response;
    }

    #[Route(
        '/api/{version}/public/plans',
        name: 'api_public_plan_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function plans(
        SubscriptionService $subscriptionService,
        SerializerService $serializerService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData([
            'plans' => json_decode($serializerService->serialize($subscriptionService->listPlans(), 'json', self::PUBLIC_GROUP)),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/public/categories',
        name: 'api_public_category_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function categories(
        CategoryRepository $categoryRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData([
            'categories' => json_decode($serializerService->serialize($categoryRepository->findBy([], ['name' => 'ASC']), 'json', self::PUBLIC_GROUP)),
        ]);

        return $response;
    }
}
