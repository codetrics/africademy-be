<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommunityComment;
use App\Entity\CommunityPost;
use App\Entity\CommunityPostLike;
use App\Entity\User;
use App\Enum\CommunityPostStatus;
use App\Enum\CommunityPostTag;
use App\Exceptions\CommunityException;
use App\Repository\CommunityCommentRepository;
use App\Repository\CommunityPostLikeRepository;
use App\Repository\CommunityPostRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

class CommunityService
{
    public function __construct(
        private readonly CommunityPostRepository $communityPostRepository,
        private readonly CommunityCommentRepository $communityCommentRepository,
        private readonly CommunityPostLikeRepository $communityPostLikeRepository,
        private readonly CommunityImageUploadService $communityImageUploadService,
    ) {
    }

    public function createPost(User $author, CommunityPostTag $tag, string $title, string $body, ?string $linkUrl): CommunityPost
    {
        $post = new CommunityPost();
        $post->setAuthor($author);
        $post->setTag($tag);
        $post->setTitle($title);
        $post->setBody($body);
        $post->setLinkUrl($linkUrl);
        $this->communityPostRepository->save($post, true);

        return $post;
    }

    public function updatePost(CommunityPost $post, CommunityPostTag $tag, string $title, string $body, ?string $linkUrl): CommunityPost
    {
        $post->setTag($tag);
        $post->setTitle($title);
        $post->setBody($body);
        $post->setLinkUrl($linkUrl);
        $this->communityPostRepository->save($post, true);

        return $post;
    }

    public function deletePost(CommunityPost $post): void
    {
        $imagePath = $post->getImagePath();
        $this->communityPostRepository->remove($post, true);

        if (!is_null($imagePath)) {
            $this->communityImageUploadService->delete($imagePath);
        }
    }

    public function hidePost(CommunityPost $post): CommunityPost
    {
        $post->setStatus(CommunityPostStatus::Hidden);
        $this->communityPostRepository->save($post, true);

        return $post;
    }

    public function setPostImage(CommunityPost $post, UploadedFile $file): CommunityPost
    {
        $storedPath = $this->communityImageUploadService->store($file, $post->getImagePath());
        $post->setImagePath($storedPath);
        $this->communityPostRepository->save($post, true);

        return $post;
    }

    public function feedQueryBuilder(?CommunityPostTag $tag, ?string $search): QueryBuilder
    {
        return $this->communityPostRepository->createFeedQueryBuilder($tag, $search);
    }

    /**
     * @return array<int, array{tag: string, count: int}>
     */
    public function trendingTopics(): array
    {
        return array_map(
            static fn (array $row): array => [
                'tag' => $row['tag']->value,
                'count' => $row['count'],
            ],
            $this->communityPostRepository->trendingTopics(),
        );
    }

    /**
     * @throws CommunityException
     */
    public function resolvePost(Ulid $publicId): CommunityPost
    {
        $post = $this->communityPostRepository->findOneByPublicId($publicId);

        if (is_null($post)) {
            throw CommunityException::postNotFound();
        }

        return $post;
    }

    /**
     * @throws CommunityException
     */
    public function resolveComment(Ulid $publicId): CommunityComment
    {
        $comment = $this->communityCommentRepository->findOneByPublicId($publicId);

        if (is_null($comment)) {
            throw CommunityException::commentNotFound();
        }

        return $comment;
    }

    public function addComment(User $author, CommunityPost $post, string $body): CommunityComment
    {
        $comment = new CommunityComment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setBody($body);
        $this->communityCommentRepository->save($comment, true);

        $this->recalculateCommentCount($post);

        return $comment;
    }

    public function deleteComment(CommunityComment $comment): void
    {
        $post = $comment->getPost();
        $this->communityCommentRepository->remove($comment, true);

        $this->recalculateCommentCount($post);
    }

    public function postCommentsQueryBuilder(CommunityPost $post): QueryBuilder
    {
        return $this->communityCommentRepository->createPostCommentsQueryBuilder($post);
    }

    /**
     * Toggles the user's like on the post and returns the resulting state.
     *
     * @return array{liked: bool, like_count: int}
     */
    public function toggleLike(User $user, CommunityPost $post): array
    {
        $existing = $this->communityPostLikeRepository->findOneByPostAndUser($post, $user);

        if ($existing instanceof CommunityPostLike) {
            $this->communityPostLikeRepository->remove($existing, true);
            $liked = false;
        } else {
            $like = new CommunityPostLike();
            $like->setPost($post);
            $like->setUser($user);
            $this->communityPostLikeRepository->save($like, true);
            $liked = true;
        }

        $likeCount = $this->communityPostLikeRepository->countByPost($post);
        $post->setLikeCount($likeCount);
        $this->communityPostRepository->save($post, true);

        return ['liked' => $liked, 'like_count' => $likeCount];
    }

    private function recalculateCommentCount(CommunityPost $post): void
    {
        $post->setCommentCount($this->communityCommentRepository->countByPost($post));
        $this->communityPostRepository->save($post, true);
    }
}
