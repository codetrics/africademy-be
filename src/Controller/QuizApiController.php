<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Course;
use App\Service\Helper\Tools;
use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\QuizException;
use App\Repository\CourseRepository;
use App\Security\Voter\CourseVoter;
use App\Service\QuizService;
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

final class QuizApiController extends AbstractController
{
    #[Route(
        '/api/{version}/facilitator/courses/{id}/quiz',
        name: 'api_quiz_upsert',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function upsert(
        Request $request,
        CourseRepository $courseRepository,
        QuizService $quizService,
        SerializerService $serializerService,
    ): JsonResponse {
        $course = $courseRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$course instanceof Course) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Course not found', Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(CourseVoter::EDIT, $course);

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $quiz = $quizService->createOrReplace($course, $data);
        } catch (QuizException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['quiz' => json_decode($serializerService->serialize($quiz))]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}/quiz',
        name: 'api_quiz_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        QuizService $quizService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $quiz = $quizService->getForCourse($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (QuizException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['quiz' => json_decode($serializerService->serialize($quiz))]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/courses/{id}/quiz/attempts',
        name: 'api_quiz_attempt_create',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function submit(
        Request $request,
        QuizService $quizService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (!array_key_exists('answers', $data) || !is_array($data['answers'])) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'Missing required key: answers', Response::HTTP_BAD_REQUEST);
        }

        try {
            $attempt = $quizService->submit($user, Ulid::fromString($request->attributes->getString('id')), $data['answers']);
        } catch (QuizException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['attempt' => json_decode($serializerService->serialize($attempt))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/students/courses/{id}/quiz/attempts',
        name: 'api_quiz_attempt_list',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function attempts(
        Request $request,
        QuizService $quizService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $queryBuilder = $quizService->studentAttemptsQueryBuilder($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (QuizException $exception) {
            return $this->mapException($exception);
        }

        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), Tools::clampLimit($request->query->getInt('limit', 10)));

        $response = new JsonResponse();
        $response->setData([
            'attempts' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decode(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }

    private function mapException(QuizException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
    }

    private function narrowUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(JsonExceptionResponse::ERROR_UNAUTHORIZED, $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
