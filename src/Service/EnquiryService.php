<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enquiry;
use App\Repository\EnquiryRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EnquiryService
{
    private const string ENQUIRY_TEMPLATE = 'email/enquiry.html.twig';

    public function __construct(
        private readonly EnquiryRepository $enquiryRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%app.enquiry.recipient%')] private readonly string $recipient,
    ) {
    }

    /**
     * Stores a website enquiry and queues a notification email to the admin
     * recipient. Persisting and queuing share a transaction so an enquiry is
     * never recorded without its notification (or vice versa).
     */
    public function enquiriesQueryBuilder(?DateTime $from, ?DateTime $to): QueryBuilder
    {
        return $this->enquiryRepository->createAdminQueryBuilder($from, $to);
    }

    public function submit(Enquiry $enquiry): Enquiry
    {
        $this->entityManager->beginTransaction();
        try {
            $this->enquiryRepository->save($enquiry, true);

            $this->notificationService->createEmailNotification(
                [$this->recipient],
                mb_substr(sprintf('New enquiry: %s', $enquiry->getSubject()), 0, 150),
                self::ENQUIRY_TEMPLATE,
                [
                    'full_name' => $enquiry->getFullName(),
                    'email' => $enquiry->getEmail(),
                    'subject' => $enquiry->getSubject(),
                    'message' => $enquiry->getMessage(),
                ],
            );

            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $enquiry;
    }
}
