<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\User;
use App\Enum\LessonStatus;
use App\Enum\LessonType;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Security\Voter\CourseVoter;
use App\Service\AccessService;
use App\Service\Helper\Tools;
use App\Service\LessonService;
use App\Service\LessonVideoUploadService;
use App\Service\SerializerService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LessonApiController extends AbstractController
{
    #[Route(
        '/api/{version}/courses/{courseId}/lessons',
        name: 'api_lesson_list',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $course = $this->resolveVisibleCourse($request->attributes->getString('courseId'), $user, $courseRepository);
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $lessons = $lessonRepository->findByCourseOrdered($course);

        // Non-owners only see published lessons.
        if ($course->getOwner()->getId() !== $user->getId()) {
            $lessons = array_values(array_filter(
                $lessons,
                static fn (Lesson $lesson): bool => $lesson->getStatus() === LessonStatus::Published,
            ));
        }

        $response = new JsonResponse();
        $response->setData(['lessons' => json_decode($serializerService->serialize($lessons))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons',
        name: 'api_lesson_create',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function create(
        Request $request,
        CourseRepository $courseRepository,
        LessonService $lessonService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
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

        try {
            Tools::checkExpectedKeys(['title', 'type'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_REQUEST,
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $lesson = new Lesson();
        $lesson->setTitle((string) $data['title']);

        $applyError = $this->applyWritableFields($lesson, $data);
        if (!is_null($applyError)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $applyError,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $violation = $this->firstViolation($lesson, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lessonService->addToCourse($course, $lesson);

        $response = new JsonResponse();
        $response->setData(['lesson' => json_decode($serializerService->serialize($lesson))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}',
        name: 'api_lesson_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $course = $this->resolveVisibleCourse($request->attributes->getString('courseId'), $user, $courseRepository);
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );

        $isOwner = $course->getOwner()->getId() === $user->getId();
        if (!$lesson instanceof Lesson || (!$isOwner && $lesson->getStatus() !== LessonStatus::Published)) {
            return $this->lessonNotFound();
        }

        $response = new JsonResponse();
        $response->setData(['lesson' => json_decode($serializerService->serialize($lesson))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}',
        name: 'api_lesson_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function update(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        LessonService $lessonService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );
        if (!$lesson instanceof Lesson) {
            return $this->lessonNotFound();
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_INVALID_JSON,
                'Invalid JSON payload',
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (array_key_exists('title', $data)) {
            $lesson->setTitle((string) $data['title']);
        }

        $applyError = $this->applyWritableFields($lesson, $data);
        if (!is_null($applyError)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $applyError,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $violation = $this->firstViolation($lesson, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_VALIDATION,
                $violation,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lessonService->update($lesson);

        $response = new JsonResponse();
        $response->setData(['lesson' => json_decode($serializerService->serialize($lesson))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}',
        name: 'api_lesson_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        LessonService $lessonService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );
        if (!$lesson instanceof Lesson) {
            return $this->lessonNotFound();
        }

        $lessonService->delete($lesson);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}/video',
        name: 'api_lesson_video_post',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function uploadVideo(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        LessonVideoUploadService $lessonVideoUploadService,
        LessonService $lessonService,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );
        if (!$lesson instanceof Lesson) {
            return $this->lessonNotFound();
        }

        $file = $request->files->get('video');
        if (!$file instanceof UploadedFile) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'No video file was uploaded.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $storedPath = $lessonVideoUploadService->store($file, $lesson->getVideoPath());
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lesson->setVideoPath($storedPath);
        $lessonService->update($lesson);

        $response = new JsonResponse();
        $response->setData(['lesson' => json_decode($serializerService->serialize($lesson))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}/video',
        name: 'api_lesson_video_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function deleteVideo(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        LessonVideoUploadService $lessonVideoUploadService,
        LessonService $lessonService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );
        if (!$lesson instanceof Lesson) {
            return $this->lessonNotFound();
        }

        $videoPath = $lesson->getVideoPath();
        if (is_null($videoPath)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'No video to delete.', Response::HTTP_NOT_FOUND);
        }

        $lessonVideoUploadService->delete($videoPath);
        $lesson->setVideoPath(null);
        $lessonService->update($lesson);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/courses/{courseId}/lessons/{id}/video',
        name: 'api_lesson_video_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'courseId' => Requirement::ULID, 'id' => Requirement::ULID],
        defaults: ['_format' => 'json', 'version' => 'v1'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function getVideo(
        Request $request,
        CourseRepository $courseRepository,
        LessonRepository $lessonRepository,
        LessonVideoUploadService $lessonVideoUploadService,
        AccessService $accessService,
    ): Response {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('courseId')));
        if (!$course instanceof Course) {
            return $this->courseNotFound();
        }

        $lesson = $lessonRepository->findOneByPublicIdAndCourse(
            Ulid::fromString($request->attributes->getString('id')),
            $course,
        );
        if (!$lesson instanceof Lesson) {
            return $this->lessonNotFound();
        }

        // Owners stream their own content freely; everyone else needs a published
        // lesson and a valid entitlement to the course (same gate as learning).
        $isOwner = $course->getOwner()->getId() === $user->getId();
        if (!$isOwner && ($lesson->getStatus() !== LessonStatus::Published || !$accessService->hasAccess($user, $course))) {
            throw $this->createAccessDeniedException('You do not have access to this lesson video.');
        }

        $videoPath = $lesson->getVideoPath();
        if (is_null($videoPath)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'No video set for this lesson.', Response::HTTP_NOT_FOUND);
        }

        try {
            $absolutePath = $lessonVideoUploadService->getAbsolutePath($videoPath);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        // Lets Apache (mod_xsendfile) stream the bytes when available; otherwise
        // Symfony streams it directly. Both honour HTTP Range requests.
        BinaryFileResponse::trustXSendfileTypeHeader();

        return new BinaryFileResponse($absolutePath);
    }

    private function narrowUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function resolveVisibleCourse(string $courseId, User $user, CourseRepository $courseRepository): ?Course
    {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($courseId));

        if (!$course instanceof Course) {
            return null;
        }

        if ($course->getStatus()->value !== 'published' && $course->getOwner()->getId() !== $user->getId()) {
            return null;
        }

        return $course;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyWritableFields(Lesson $lesson, array $data): ?string
    {
        if (array_key_exists('type', $data)) {
            $type = LessonType::tryFrom((string) $data['type']);
            if (is_null($type)) {
                return 'Invalid lesson type.';
            }
            $lesson->setType($type);
        }

        if (array_key_exists('status', $data)) {
            $status = LessonStatus::tryFrom((string) $data['status']);
            if (is_null($status)) {
                return 'Invalid lesson status.';
            }
            $lesson->setStatus($status);
        }

        if (array_key_exists('body', $data)) {
            $lesson->setBody(is_null($data['body']) ? null : (string) $data['body']);
        }

        if (array_key_exists('content_ref', $data)) {
            $lesson->setContentRef(is_null($data['content_ref']) ? null : (string) $data['content_ref']);
        }

        if (array_key_exists('duration_minutes', $data)) {
            $lesson->setDurationMinutes(is_null($data['duration_minutes']) ? null : (int) $data['duration_minutes']);
        }

        if (array_key_exists('position', $data)) {
            $lesson->setPosition((int) $data['position']);
        }

        return null;
    }

    private function firstViolation(Lesson $lesson, ValidatorInterface $validator): ?string
    {
        foreach ($validator->validate($lesson) as $violation) {
            return (string) $violation->getMessage();
        }

        return null;
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

    private function courseNotFound(): JsonExceptionResponse
    {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_NOT_FOUND,
            'Course not found',
            Response::HTTP_NOT_FOUND,
        );
    }

    private function lessonNotFound(): JsonExceptionResponse
    {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_NOT_FOUND,
            'Lesson not found',
            Response::HTTP_NOT_FOUND,
        );
    }
}
