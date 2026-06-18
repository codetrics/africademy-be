<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use App\Exceptions\ReviewException;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\ReviewService;
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

final class ReviewApiController extends AbstractController
{
    #[Route(
        '/api/{version}/courses/{id}/reviews',
        name: 'api_review_list',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        ReviewService $reviewService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $queryBuilder = $reviewService->courseReviewsQueryBuilder(Ulid::fromString($request->attributes->getString('id')));
        } catch (ReviewException $exception) {
            return $this->mapException($exception);
        }

        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), $request->query->getInt('limit', 10));

        $response = new JsonResponse();
        $response->setData([
            'reviews' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/courses/{id}/reviews',
        name: 'api_review_create',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function create(
        Request $request,
        ReviewService $reviewService,
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

        if (!array_key_exists('rating', $data)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'Missing required key: rating', Response::HTTP_BAD_REQUEST);
        }

        try {
            $review = $reviewService->create(
                $user,
                Ulid::fromString($request->attributes->getString('id')),
                (int) $data['rating'],
                $this->body($data),
            );
        } catch (ReviewException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['review' => json_decode($serializerService->serialize($review))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/reviews/{id}',
        name: 'api_review_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function update(
        Request $request,
        ReviewService $reviewService,
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

        try {
            $review = $reviewService->getStudentReview($user, Ulid::fromString($request->attributes->getString('id')));
            $rating = array_key_exists('rating', $data) ? (int) $data['rating'] : $review->getRating();
            $body = array_key_exists('body', $data) ? $this->body($data) : $review->getBody();
            $reviewService->update($review, $rating, $body);
        } catch (ReviewException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['review' => json_decode($serializerService->serialize($review))]);

        return $response;
    }

    #[Route(
        '/api/{version}/reviews/{id}',
        name: 'api_review_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        ReviewService $reviewService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $review = $reviewService->getStudentReview($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (ReviewException $exception) {
            return $this->mapException($exception);
        }

        $reviewService->delete($review);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function body(array $data): ?string
    {
        return array_key_exists('body', $data) && !is_null($data['body']) ? (string) $data['body'] : null;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decode(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }

    private function mapException(ReviewException $exception): JsonExceptionResponse
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
