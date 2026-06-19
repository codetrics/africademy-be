<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\CommunityPostTag;
use App\Exceptions\CommunityException;
use App\Exceptions\JsonExceptionResponse;
use App\Security\Voter\CommunityCommentVoter;
use App\Security\Voter\CommunityPostVoter;
use App\Service\CommunityImageUploadService;
use App\Service\CommunityService;
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

final class CommunityApiController extends AbstractController
{
    #[Route(
        '/api/{version}/community/posts',
        name: 'api_community_post_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        CommunityService $communityService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $tagValue = $request->query->getString('tag');
        $tag = $tagValue === '' ? null : CommunityPostTag::tryFrom($tagValue);

        if ($tagValue !== '' && is_null($tag)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid tag filter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $queryBuilder = $communityService->feedQueryBuilder($tag, $request->query->getString('q'));
        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), Tools::clampLimit($request->query->getInt('limit', 10)));

        $response = new JsonResponse();
        $response->setData([
            'posts' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/trending',
        name: 'api_community_trending',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function trending(
        CommunityService $communityService,
    ): JsonResponse {
        $response = new JsonResponse();
        $response->setData(['topics' => $communityService->trendingTopics()]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts',
        name: 'api_community_post_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function create(
        Request $request,
        CommunityService $communityService,
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
            Tools::checkExpectedKeys(['tag', 'title', 'body'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $tag = CommunityPostTag::tryFrom((string) $data['tag']);
        if (is_null($tag)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid post tag.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $post = $communityService->createPost(
                $user,
                $tag,
                (string) $data['title'],
                (string) $data['body'],
                $this->linkUrl($data),
            );
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}',
        name: 'api_community_post_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function show(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $communityService->resolveVisiblePost(Ulid::fromString($request->attributes->getString('id')), $this->narrowUser());
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}',
        name: 'api_community_post_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function update(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $communityService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(CommunityPostVoter::EDIT, $post);

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $tag = $post->getTag();
        if (array_key_exists('tag', $data)) {
            $tag = CommunityPostTag::tryFrom((string) $data['tag']);
            if (is_null($tag)) {
                return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid post tag.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $title = array_key_exists('title', $data) ? (string) $data['title'] : $post->getTitle();
        $body = array_key_exists('body', $data) ? (string) $data['body'] : $post->getBody();
        $linkUrl = array_key_exists('link_url', $data) ? $this->linkUrl($data) : $post->getLinkUrl();

        try {
            $communityService->updatePost($post, $tag, $title, $body, $linkUrl);
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}',
        name: 'api_community_post_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        CommunityService $communityService,
    ): JsonResponse {
        try {
            $post = $communityService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(CommunityPostVoter::DELETE, $post);

        $communityService->deletePost($post);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/{version}/community/posts/{id}/hide',
        name: 'api_community_post_hide',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function hide(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        return $this->moderate($request, $communityService, $serializerService, true);
    }

    #[Route(
        '/api/{version}/community/posts/{id}/unhide',
        name: 'api_community_post_unhide',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function unhide(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        return $this->moderate($request, $communityService, $serializerService, false);
    }

    #[Route(
        '/api/{version}/community/posts/{id}/image',
        name: 'api_community_post_image_post',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function uploadImage(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $communityService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(CommunityPostVoter::EDIT, $post);

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'No image file was uploaded.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $communityService->setPostImage($post, $file);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}/image',
        name: 'api_community_post_image_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json', 'version' => 'v1'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function getImage(
        Request $request,
        CommunityService $communityService,
        CommunityImageUploadService $communityImageUploadService,
    ): Response {
        try {
            $post = $communityService->resolveVisiblePost(Ulid::fromString($request->attributes->getString('id')), $this->narrowUser());
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $imagePath = $post->getImagePath();
        if (is_null($imagePath)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'No image set for this post.', Response::HTTP_NOT_FOUND);
        }

        try {
            $absolutePath = $communityImageUploadService->getAbsolutePath($imagePath);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($absolutePath);
    }

    #[Route(
        '/api/{version}/community/posts/{id}/like',
        name: 'api_community_post_like',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function toggleLike(
        Request $request,
        CommunityService $communityService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $post = $communityService->resolveVisiblePost(Ulid::fromString($request->attributes->getString('id')), $this->narrowUser());
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData($communityService->toggleLike($user, $post));

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}/comments',
        name: 'api_community_comment_list',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function listComments(
        Request $request,
        CommunityService $communityService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $post = $communityService->resolveVisiblePost(Ulid::fromString($request->attributes->getString('id')), $this->narrowUser());
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $pagination = $paginator->paginate(
            $communityService->postCommentsQueryBuilder($post),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 20)),
        );

        $response = new JsonResponse();
        $response->setData([
            'comments' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/community/posts/{id}/comments',
        name: 'api_community_comment_create',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function createComment(
        Request $request,
        CommunityService $communityService,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $post = $communityService->resolveVisiblePost(Ulid::fromString($request->attributes->getString('id')), $this->narrowUser());
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            Tools::checkExpectedKeys(['body'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $comment = $communityService->addComment($user, $post, (string) $data['body']);
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['comment' => json_decode($serializerService->serialize($comment))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/community/comments/{id}',
        name: 'api_community_comment_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function deleteComment(
        Request $request,
        CommunityService $communityService,
    ): JsonResponse {
        try {
            $comment = $communityService->resolveComment(Ulid::fromString($request->attributes->getString('id')));
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(CommunityCommentVoter::DELETE, $comment);

        $communityService->deleteComment($comment);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function linkUrl(array $data): ?string
    {
        return array_key_exists('link_url', $data) && !is_null($data['link_url']) ? (string) $data['link_url'] : null;
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

    private function moderate(Request $request, CommunityService $communityService, SerializerService $serializerService, bool $hide): JsonResponse
    {
        try {
            $post = $communityService->resolvePost(Ulid::fromString($request->attributes->getString('id')));
        } catch (CommunityException $exception) {
            return $this->mapException($exception);
        }

        $this->denyAccessUnlessGranted(CommunityPostVoter::MODERATE, $post);

        $hide ? $communityService->hidePost($post) : $communityService->unhidePost($post);

        $response = new JsonResponse();
        $response->setData(['post' => json_decode($serializerService->serialize($post))]);

        return $response;
    }

    private function mapException(CommunityException $exception): JsonExceptionResponse
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
