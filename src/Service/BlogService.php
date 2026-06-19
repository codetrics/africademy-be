<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlogCategory;
use App\Entity\BlogPost;
use App\Entity\User;
use App\Enum\BlogPostStatus;
use App\Exceptions\BlogException;
use App\Repository\BlogCategoryRepository;
use App\Repository\BlogPostRepository;
use DateTime;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Ulid;

class BlogService
{
    private const int WORDS_PER_MINUTE = 200;

    public function __construct(
        private readonly BlogPostRepository $blogPostRepository,
        private readonly BlogCategoryRepository $blogCategoryRepository,
        private readonly BlogCoverUploadService $blogCoverUploadService,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function createPost(User $author, BlogCategory $category, string $title, string $excerpt, string $body): BlogPost
    {
        $post = new BlogPost();
        $post->setAuthor($author);
        $post->setCategory($category);
        $post->setTitle($title);
        $post->setExcerpt($excerpt);
        $post->setBody($body);
        $post->setReadTimeMinutes($this->estimateReadTime($body));
        $post->setSlug($this->generateUniqueSlug($title));
        $this->blogPostRepository->save($post, true);

        return $post;
    }

    public function updatePost(BlogPost $post, BlogCategory $category, string $title, string $excerpt, string $body, bool $isFeatured): BlogPost
    {
        $post->setCategory($category);
        $post->setTitle($title);
        $post->setExcerpt($excerpt);
        $post->setBody($body);
        $post->setReadTimeMinutes($this->estimateReadTime($body));
        $post->setIsFeatured($isFeatured);
        $this->blogPostRepository->save($post, true);

        return $post;
    }

    public function publish(BlogPost $post): BlogPost
    {
        $post->setStatus(BlogPostStatus::Published);

        if (is_null($post->getPublishedAt())) {
            $post->setPublishedAt(new DateTime());
        }

        $this->blogPostRepository->save($post, true);

        return $post;
    }

    public function unpublish(BlogPost $post): BlogPost
    {
        $post->setStatus(BlogPostStatus::Draft);
        $this->blogPostRepository->save($post, true);

        return $post;
    }

    public function deletePost(BlogPost $post): void
    {
        $coverPath = $post->getCoverImagePath();
        $this->blogPostRepository->remove($post, true);

        if (!is_null($coverPath)) {
            $this->blogCoverUploadService->delete($coverPath);
        }
    }

    public function setCover(BlogPost $post, UploadedFile $file): BlogPost
    {
        $storedPath = $this->blogCoverUploadService->store($file, $post->getCoverImagePath());
        $post->setCoverImagePath($storedPath);
        $this->blogPostRepository->save($post, true);

        return $post;
    }

    public function publishedQueryBuilder(?BlogCategory $category): QueryBuilder
    {
        return $this->blogPostRepository->createPublishedQueryBuilder($category);
    }

    public function findCategoryBySlug(string $slug): ?BlogCategory
    {
        return $this->blogCategoryRepository->findOneBySlug($slug);
    }

    /**
     * @throws BlogException
     */
    public function resolvePost(Ulid $publicId): BlogPost
    {
        $post = $this->blogPostRepository->findOneByPublicId($publicId);

        if (is_null($post)) {
            throw BlogException::postNotFound();
        }

        return $post;
    }

    /**
     * @throws BlogException
     */
    public function resolvePublishedPostBySlug(string $slug): BlogPost
    {
        $post = $this->blogPostRepository->findOnePublishedBySlug($slug);

        if (is_null($post)) {
            throw BlogException::postNotFound();
        }

        return $post;
    }

    /**
     * @return BlogCategory[]
     */
    public function listCategories(): array
    {
        return $this->blogCategoryRepository->findBy([], ['name' => 'ASC']);
    }

    public function createCategory(string $name): BlogCategory
    {
        $category = new BlogCategory();
        $category->setName($name);
        $category->setSlug($this->generateUniqueCategorySlug($name));
        $this->blogCategoryRepository->save($category, true);

        return $category;
    }

    public function updateCategory(BlogCategory $category, string $name): BlogCategory
    {
        $category->setName($name);
        $this->blogCategoryRepository->save($category, true);

        return $category;
    }

    /**
     * @throws BlogException
     */
    public function deleteCategory(BlogCategory $category): void
    {
        if (!is_null($this->blogPostRepository->findOneBy(['category' => $category]))) {
            throw BlogException::categoryInUse();
        }

        $this->blogCategoryRepository->remove($category, true);
    }

    /**
     * @throws BlogException
     */
    public function resolveCategory(Ulid $publicId): BlogCategory
    {
        $category = $this->blogCategoryRepository->findOneByPublicId($publicId);

        if (is_null($category)) {
            throw BlogException::categoryNotFound();
        }

        return $category;
    }

    private function estimateReadTime(string $body): int
    {
        $wordCount = str_word_count(strip_tags($body));

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();

        if ($base === '') {
            $base = 'post';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->blogPostRepository->slugExists($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $slug;
    }

    private function generateUniqueCategorySlug(string $name): string
    {
        $base = $this->slugger->slug($name)->lower()->toString();

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->blogCategoryRepository->slugExists($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
