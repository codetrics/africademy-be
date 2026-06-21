<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByPublicId(Ulid $publicId): ?User
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    /**
     * Admin student directory, newest first, with an optional free-text search
     * across email and profile name, and an optional account-status filter.
     */
    public function createAdminQueryBuilder(?string $search, ?UserStatus $status): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('appUser')
            ->innerJoin('appUser.profile', 'profile')
            ->addSelect('profile')
            ->orderBy('appUser.createdAt', 'DESC');

        if (!is_null($search) && $search !== '') {
            $queryBuilder->andWhere('appUser.email LIKE :search OR profile.firstName LIKE :search OR profile.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if (!is_null($status)) {
            $queryBuilder->andWhere('appUser.status = :status')
                ->setParameter('status', $status);
        }

        return $queryBuilder;
    }

    /**
     * Admin teacher directory: users holding ROLE_TEACHER, newest first, with an
     * optional free-text search and an optional account-status filter.
     */
    public function createTeacherQueryBuilder(?string $search, ?UserStatus $status): QueryBuilder
    {
        $queryBuilder = $this->createAdminQueryBuilder($search, $status)
            ->andWhere('appUser.roles LIKE :teacherRole')
            ->setParameter('teacherRole', '%"' . User::ROLE_TEACHER . '"%');

        return $queryBuilder;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }
}
