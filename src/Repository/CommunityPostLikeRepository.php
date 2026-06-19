<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommunityPost;
use App\Entity\CommunityPostLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityPostLike>
 *
 * @method CommunityPostLike|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityPostLike|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityPostLike[]    findAll()
 * @method CommunityPostLike[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityPostLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityPostLike::class);
    }

    public function save(CommunityPostLike $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommunityPostLike $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPostAndUser(CommunityPost $post, User $user): ?CommunityPostLike
    {
        return $this->findOneBy(['post' => $post, 'user' => $user]);
    }

    public function countByPost(CommunityPost $post): int
    {
        return (int) $this->createQueryBuilder('postLike')
            ->select('COUNT(postLike.id)')
            ->where('postLike.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
