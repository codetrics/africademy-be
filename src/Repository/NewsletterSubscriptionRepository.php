<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscription>
 *
 * @method NewsletterSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewsletterSubscription[]    findAll()
 * @method NewsletterSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsletterSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscription::class);
    }

    public function save(NewsletterSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NewsletterSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByEmail(string $email): ?NewsletterSubscription
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByUnsubscribeToken(string $token): ?NewsletterSubscription
    {
        return $this->findOneBy(['unsubscribeToken' => $token]);
    }

    public function findOneByConfirmationToken(string $token): ?NewsletterSubscription
    {
        return $this->findOneBy(['confirmationToken' => $token]);
    }
}
