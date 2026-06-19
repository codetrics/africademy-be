<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommunityComment;
use App\Entity\CommunityPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<CommunityComment>
 *
 * @method CommunityComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityComment[]    findAll()
 * @method CommunityComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityComment::class);
    }

    public function save(CommunityComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommunityComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?CommunityComment
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function createPostCommentsQueryBuilder(CommunityPost $post): QueryBuilder
    {
        return $this->createQueryBuilder('comment')
            ->where('comment.post = :post')
            ->setParameter('post', $post)
            ->orderBy('comment.createdAt', 'ASC');
    }

    public function countByPost(CommunityPost $post): int
    {
        return (int) $this->createQueryBuilder('comment')
            ->select('COUNT(comment.id)')
            ->where('comment.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
