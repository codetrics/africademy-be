<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\Money;
use App\Entity\User;
use App\Enum\CourseLevel;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\CategoryRepository;
use App\Repository\CourseRepository;
use App\Security\Voter\CourseVoter;
use App\Service\CourseService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        $user = $this->getUser();
        if (!$user instanceof User) {
            $exception = $this->createAccessDeniedException();
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_UNAUTHORIZED,
                $exception->getMessage(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $categorySlug = $request->query->getString('category');
        $levelValue = $request->query->getString('level');
        $search = $request->query->getString('q');

        $category = $categorySlug !== '' ? $categoryRepository->findOneBySlug($categorySlug) : null;
        $level = $levelValue !== '' ? CourseLevel::tryFrom($levelValue) : null;

        // A filter that resolves to nothing yields an empty page rather than an unfiltered one.
        if (($categorySlug !== '' && !$category instanceof Category) || ($levelValue !== '' && is_null($level))) {
            return $this->paginatedCoursesResponse([], 0, $page, $limit, $serializerService);
        }

        $owner = $this->isGranted(User::ROLE_TEACHER) ? $user : null;
        $queryBuilder = $courseRepository->createCatalogQueryBuilder($category, $level, $search, $owner);

        $pagination = $paginator->paginate($queryBuilder, $page, $limit);

        $coursesJSON = $serializerService->serialize($pagination->getItems());
        $paginationJSON = $serializerService->serialize(new PaginationReturnType($pagination));

        $response = new JsonResponse();
        $response->setData([
            'courses' => json_decode($coursesJSON),
            'pagination' => json_decode($paginationJSON),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses',
        name: 'api_course_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(User::ROLE_TEACHER)]
    public function create(
        Request $request,
        CategoryRepository $categoryRepository,
        CourseService $courseService,
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            Tools::checkExpectedKeys(['title', 'category_id'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $category = $this->resolveCategory((string) $data['category_id'], $categoryRepository);
        if (!$category instanceof Category) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                'The selected category does not exist.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $course = new Course();
        $course->setOwner($user);
        $course->setCategory($category);
        $course->setTitle((string) $data['title']);

        $applyError = $this->applyWritableFields($course, $data);
        if (!is_null($applyError)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $applyError,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $violation = $this->firstViolation($course, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $courseService->create($course);

        $courseJSON = $serializerService->serialize($course);
        $response = new JsonResponse();
        $response->setData(['course' => json_decode($courseJSON)]);
        $response->setStatusCode(Response::HTTP_CREATED);

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
        $user = $this->getUser();
        if (!$user instanceof User) {
            $exception = $this->createAccessDeniedException();
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_UNAUTHORIZED,
                $exception->getMessage(),
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));

        // Drafts are only visible to their owner; hide existence otherwise.
        if (!$course instanceof Course || ($course->getStatus()->value !== 'published' && $course->getOwner()->getId() !== $user->getId())) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'Course not found',
                Response::HTTP_NOT_FOUND,
            );
        }

        $courseJSON = $serializerService->serialize($course);
        $response = new JsonResponse();
        $response->setData(['course' => json_decode($courseJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}',
        name: 'api_course_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function update(
        Request $request,
        CourseRepository $courseRepository,
        CategoryRepository $categoryRepository,
        CourseService $courseService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'Course not found',
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (array_key_exists('category_id', $data)) {
            $category = $this->resolveCategory((string) $data['category_id'], $categoryRepository);
            if (!$category instanceof Category) {
                return new JsonExceptionResponse(
                    JsonExceptionResponse::ERROR_VALIDATION,
                    'The selected category does not exist.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $course->setCategory($category);
        }

        if (array_key_exists('title', $data)) {
            $course->setTitle((string) $data['title']);
        }

        $applyError = $this->applyWritableFields($course, $data);
        if (!is_null($applyError)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $applyError,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $violation = $this->firstViolation($course, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $courseService->update($course);

        $courseJSON = $serializerService->serialize($course);
        $response = new JsonResponse();
        $response->setData(['course' => json_decode($courseJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}/publish',
        name: 'api_course_publish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function publish(
        Request $request,
        CourseRepository $courseRepository,
        CourseService $courseService,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'Course not found',
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->denyAccessUnlessGranted(CourseVoter::PUBLISH, $course);

        $courseService->publish($course);

        $courseJSON = $serializerService->serialize($course);
        $response = new JsonResponse();
        $response->setData(['course' => json_decode($courseJSON)]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}',
        name: 'api_course_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        CourseRepository $courseRepository,
        CourseService $courseService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_NOT_FOUND,
                'Course not found',
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->denyAccessUnlessGranted(CourseVoter::DELETE, $course);

        $courseService->delete($course);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveCategory(string $publicId, CategoryRepository $categoryRepository): ?Category
    {
        if (!Ulid::isValid($publicId)) {
            return null;
        }

        return $categoryRepository->findOneByPublicId(Ulid::fromString($publicId));
    }

    /**
     * Applies the optional writable fields shared by create and update.
     * Returns an error message when a value is invalid, or null on success.
     *
     * @param array<string, mixed> $data
     */
    private function applyWritableFields(Course $course, array $data): ?string
    {
        if (array_key_exists('description', $data)) {
            $course->setDescription(is_null($data['description']) ? null : (string) $data['description']);
        }

        if (array_key_exists('tagline', $data)) {
            $course->setTagline(is_null($data['tagline']) ? null : (string) $data['tagline']);
        }

        if (array_key_exists('thumbnail', $data)) {
            $course->setThumbnailPath(is_null($data['thumbnail']) ? null : (string) $data['thumbnail']);
        }

        if (array_key_exists('level', $data)) {
            if (is_null($data['level'])) {
                $course->setLevel(null);
            } else {
                $level = CourseLevel::tryFrom((string) $data['level']);
                if (is_null($level)) {
                    return 'Invalid course level.';
                }
                $course->setLevel($level);
            }
        }

        if (array_key_exists('tags', $data)) {
            if (!is_array($data['tags'])) {
                return 'Tags must be a list of strings.';
            }
            $course->setTags(array_map(static fn (mixed $tag): string => (string) $tag, $data['tags']));
        }

        if (array_key_exists('objectives', $data)) {
            if (!is_array($data['objectives'])) {
                return 'Objectives must be a list of strings.';
            }
            $course->setObjectives(array_map(static fn (mixed $objective): string => (string) $objective, $data['objectives']));
        }

        if (array_key_exists('price_amount_cents', $data)) {
            $amountCents = (int) $data['price_amount_cents'];
            if ($amountCents < 0) {
                return 'Price cannot be negative.';
            }
            $currency = array_key_exists('price_currency', $data) ? (string) $data['price_currency'] : Money::DEFAULT_CURRENCY;
            $course->setPrice(new Money($amountCents, $currency));
        }

        if (array_key_exists('is_free', $data)) {
            $course->setIsFree((bool) $data['is_free']);
        }

        if (array_key_exists('is_purchasable', $data)) {
            $course->setIsPurchasable((bool) $data['is_purchasable']);
        }

        if (array_key_exists('included_in_subscription', $data)) {
            $course->setIncludedInSubscription((bool) $data['included_in_subscription']);
        }

        return null;
    }

    private function firstViolation(Course $course, ValidatorInterface $validator): ?string
    {
        foreach ($validator->validate($course) as $violation) {
            return (string) $violation->getMessage();
        }

        return null;
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
