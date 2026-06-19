<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\BlogException;
use App\Exceptions\JsonExceptionResponse;
use App\Security\Voter\BlogPostVoter;
use App\Service\BlogCoverUploadService;
use App\Service\BlogService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
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

final class BlogApiController extends AbstractController
{
    #[Route(
        '/api/{version}/blog/posts',
        name: 'api_blog_post_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function list(
        Request $request,
        BlogService $blogService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $category = null;
        $categorySlug = $request->query->getString('category');

        if ($categorySlug !== '') {
            $category = $blogService->findCategoryBySlug($categorySlug);

            if (is_null($category)) {
                $response = new JsonResponse();
                $response->setData(['posts' => [], 'pagination' => null]);

                return $response;
            }
        }

        $pagination = $paginator->paginate(
            $blogService->publishedQueryBuilder($category),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 9),
        );

        $response = new JsonResponse();
        $response->setData([
            'posts' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/categories',
        name: 'api_blog_category_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function listCategories(
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData(['categories' => json_decode($serializerService->serialize($blogService->listCategories()))]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{slug}',
        name: 'api_blog_post_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'slug' => '[a-z0-9-]+'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function show(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePublishedPostBySlug($request->attributes->getString('slug'));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{id}/cover',
        name: 'api_blog_post_cover_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json', 'version' => 'v1'],
        methods: [Request::METHOD_GET],
    )]
    public function getCover(
        Request $request,
        BlogService $blogService,
        BlogCoverUploadService $blogCoverUploadService,
    ): Response {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $coverPath = $post->getCoverImagePath();
        if (is_null($coverPath)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'No cover set for this post.', Response::HTTP_NOT_FOUND);
        }

        try {
            $absolutePath = $blogCoverUploadService->getAbsolutePath($coverPath);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($absolutePath);
    }

    #[Route(
        '/api/{version}/blog/posts',
        name: 'api_blog_post_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function create(
        Request $request,
        BlogService $blogService,
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
            Tools::checkExpectedKeys(['category_id', 'title', 'excerpt', 'body'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $category = $blogService->resolveCategory(Ulid::fromString((string) $data['category_id']));
            $post = $blogService->createPost(
                $user,
                $category,
                (string) $data['title'],
                (string) $data['excerpt'],
                (string) $data['body'],
            );
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{id}',
        name: 'api_blog_post_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function update(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(BlogPostVoter::EDIT, $post);

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $category = $post->getCategory();
        if (array_key_exists('category_id', $data)) {
            try {
                $category = $blogService->resolveCategory(Ulid::fromString((string) $data['category_id']));
            } catch (BlogException $exception) {
                return $this->mapException($exception);
            }
        }

        $title = array_key_exists('title', $data) ? (string) $data['title'] : $post->getTitle();
        $excerpt = array_key_exists('excerpt', $data) ? (string) $data['excerpt'] : $post->getExcerpt();
        $body = array_key_exists('body', $data) ? (string) $data['body'] : $post->getBody();
        $isFeatured = array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : $post->isFeatured();

        $blogService->updatePost($post, $category, $title, $excerpt, $body, $isFeatured);

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{id}/publish',
        name: 'api_blog_post_publish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function publish(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(BlogPostVoter::PUBLISH, $post);

        $blogService->publish($post);

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{id}/unpublish',
        name: 'api_blog_post_unpublish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function unpublish(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(BlogPostVoter::PUBLISH, $post);

        $blogService->unpublish($post);

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/blog/posts/{id}',
        name: 'api_blog_post_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function delete(
        Request $request,
        BlogService $blogService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(BlogPostVoter::DELETE, $post);

        $blogService->deletePost($post);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/blog/posts/{id}/cover',
        name: 'api_blog_post_cover_post',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_TEACHER')]
    public function uploadCover(
        Request $request,
        BlogService $blogService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $blogService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (BlogException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(BlogPostVoter::EDIT, $post);

        $file = $request->files->get('cover');
        if (!$file instanceof UploadedFile) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'No cover file was uploaded.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $blogService->setCover($post, $file);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
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

    private function mapException(BlogException $exception): JsonExceptionResponse
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
