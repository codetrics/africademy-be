<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommunityPost;
use App\Enum\CommunityPostStatus;
use App\Enum\CommunityPostTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<CommunityPost>
 *
 * @method CommunityPost|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityPost|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityPost[]    findAll()
 * @method CommunityPost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityPost::class);
    }

    public function save(CommunityPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommunityPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?CommunityPost
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    /**
     * Published posts, newest first, optionally filtered by tag and a free-text
     * search across title and body.
     */
    public function createFeedQueryBuilder(?CommunityPostTag $tag, ?string $search): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->where('post.status = :status')
            ->setParameter('status', CommunityPostStatus::Published)
            ->orderBy('post.createdAt', 'DESC');

        if (!is_null($tag)) {
            $queryBuilder->andWhere('post.tag = :tag')
                ->setParameter('tag', $tag);
        }

        if (!is_null($search) && $search !== '') {
            $queryBuilder->andWhere('post.title LIKE :search OR post.body LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $queryBuilder;
    }

    /**
     * Post counts grouped by tag across published posts, busiest first.
     *
     * @return array<int, array{tag: CommunityPostTag, count: int}>
     */
    public function trendingTopics(): array
    {
        $rows = $this->createQueryBuilder('post')
            ->select('post.tag AS tag', 'COUNT(post.id) AS post_count')
            ->where('post.status = :status')
            ->setParameter('status', CommunityPostStatus::Published)
            ->groupBy('post.tag')
            ->orderBy('post_count', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'tag' => $row['tag'],
                'count' => (int) $row['post_count'],
            ],
            $rows,
        );
    }
}
