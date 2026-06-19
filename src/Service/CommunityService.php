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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CommunityService
{
    public function __construct(
        private readonly CommunityPostRepository $communityPostRepository,
        private readonly CommunityCommentRepository $communityCommentRepository,
        private readonly CommunityPostLikeRepository $communityPostLikeRepository,
        private readonly CommunityImageUploadService $communityImageUploadService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws CommunityException
     */
    public function createPost(User $author, CommunityPostTag $tag, string $title, string $body, ?string $linkUrl): CommunityPost
    {
        $post = new CommunityPost();
        $post->setAuthor($author);
        $post->setTag($tag);
        $post->setTitle($title);
        $post->setBody($body);
        $post->setLinkUrl($linkUrl);
        $this->validate($post);
        $this->communityPostRepository->save($post, true);

        return $post;
    }

    /**
     * @throws CommunityException
     */
    public function updatePost(CommunityPost $post, CommunityPostTag $tag, string $title, string $body, ?string $linkUrl): CommunityPost
    {
        $post->setTag($tag);
        $post->setTitle($title);
        $post->setBody($body);
        $post->setLinkUrl($linkUrl);
        $this->validate($post);
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

    public function unhidePost(CommunityPost $post): CommunityPost
    {
        $post->setStatus(CommunityPostStatus::Published);
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
     * Resolves a post for a public read/interaction. Hidden (moderated) posts are
     * only visible to their author or an admin; everyone else gets a not-found.
     *
     * @throws CommunityException
     */
    public function resolveVisiblePost(Ulid $publicId, ?User $viewer): CommunityPost
    {
        $post = $this->resolvePost($publicId);

        if ($post->getStatus() === CommunityPostStatus::Published) {
            return $post;
        }

        if (
            $viewer instanceof User
            && ($post->getAuthor()->getId() === $viewer->getId() || in_array(User::ROLE_ADMIN, $viewer->getRoles(), true))
        ) {
            return $post;
        }

        throw CommunityException::postNotFound();
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

    /**
     * @throws CommunityException
     */
    public function addComment(User $author, CommunityPost $post, string $body): CommunityComment
    {
        $comment = new CommunityComment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setBody($body);
        $this->validate($comment);
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

            try {
                $this->communityPostLikeRepository->save($like, true);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request from the same user already inserted the like.
                // The EntityManager is now closed, so return the in-memory count;
                // the winning request has already persisted the correct value.
                return ['liked' => true, 'like_count' => $post->getLikeCount()];
            }

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

    /**
     * @throws CommunityException on the first constraint violation
     */
    private function validate(object $entity): void
    {
        $violations = $this->validator->validate($entity);

        if (count($violations) > 0) {
            throw CommunityException::validation((string) $violations[0]->getMessage());
        }
    }
}
