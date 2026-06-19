<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogCategory;
use App\Entity\BlogPost;
use App\Enum\BlogPostStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<BlogPost>
 *
 * @method BlogPost|null find($id, $lockMode = null, $lockVersion = null)
 * @method BlogPost|null findOneBy(array $criteria, array $orderBy = null)
 * @method BlogPost[]    findAll()
 * @method BlogPost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    public function save(BlogPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BlogPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?BlogPost
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findOnePublishedBySlug(string $slug): ?BlogPost
    {
        return $this->findOneBy(['slug' => $slug, 'status' => BlogPostStatus::Published]);
    }

    public function slugExists(string $slug): bool
    {
        return (int) $this->createQueryBuilder('blogPost')
            ->select('COUNT(blogPost.id)')
            ->where('blogPost.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Published posts, featured first then newest, optionally filtered by category.
     */
    public function createPublishedQueryBuilder(?BlogCategory $category): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('blogPost')
            ->where('blogPost.status = :status')
            ->setParameter('status', BlogPostStatus::Published)
            ->orderBy('blogPost.isFeatured', 'DESC')
            ->addOrderBy('blogPost.publishedAt', 'DESC');

        if (!is_null($category)) {
            $queryBuilder->andWhere('blogPost.category = :category')
                ->setParameter('category', $category);
        }

        return $queryBuilder;
    }
}
