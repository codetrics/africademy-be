<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\BlogException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\BlogService;
use App\Service\Helper\Tools;
use App\Service\SerializerService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class AdminBlogApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/blog/categories',
        name: 'api_admin_blog_category_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function createCategory(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            Tools::checkExpectedKeys(['name'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $category = $blogService->createCategory((string) $data['name']);

        $response = new JsonResponse();
        $response->setData(['category' => json_decode($serializerService->serialize($category))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/blog/categories/{id}',
        name: 'api_admin_blog_category_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function updateCategory(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $category = $blogService->resolveCategory(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            Tools::checkExpectedKeys(['name'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $blogService->updateCategory($category, (string) $data['name']);

        $response = new JsonResponse();
        $response->setData(['category' => json_decode($serializerService->serialize($category))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/blog/categories/{id}',
        name: 'api_admin_blog_category_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(
        Request $request,
        BlogService $blogService,
    ): JsonResponse {
        try {
            $category = $blogService->resolveCategory(Ulid::fromString($request->attributes->getString('id')));
            $blogService->deleteCategory($category);
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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

    private function mapException(BlogException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
    }
}
