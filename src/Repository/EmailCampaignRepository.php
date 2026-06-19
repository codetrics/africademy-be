<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailCampaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<EmailCampaign>
 *
 * @method EmailCampaign|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailCampaign|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailCampaign[]    findAll()
 * @method EmailCampaign[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailCampaign::class);
    }

    public function save(EmailCampaign $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmailCampaign $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?EmailCampaign
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('campaign')
            ->orderBy('campaign.createdAt', 'DESC');
    }
}
