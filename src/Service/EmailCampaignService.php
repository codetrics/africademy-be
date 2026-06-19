<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailCampaign;
use App\Entity\User;
use App\Enum\CampaignSegment;
use App\Enum\EmailCampaignStatus;
use App\Exceptions\EmailCampaignException;
use App\Repository\EmailCampaignRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

class EmailCampaignService
{
    private const string CAMPAIGN_TEMPLATE = 'email/generic.html.twig';

    public function __construct(
        private readonly EmailCampaignRepository $emailCampaignRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createCampaign(?User $creator, string $subject, string $heading, string $body, CampaignSegment $segment): EmailCampaign
    {
        $campaign = new EmailCampaign();
        $campaign->setCreatedBy($creator);
        $campaign->setSubject($subject);
        $campaign->setHeading($heading);
        $campaign->setBody($body);
        $campaign->setSegment($segment);
        $this->emailCampaignRepository->save($campaign, true);

        return $campaign;
    }

    public function listQueryBuilder(): QueryBuilder
    {
        return $this->emailCampaignRepository->createListQueryBuilder();
    }

    /**
     * @throws EmailCampaignException
     */
    public function resolveCampaign(Ulid $publicId): EmailCampaign
    {
        $campaign = $this->emailCampaignRepository->findOneByPublicId($publicId);

        if (is_null($campaign)) {
            throw EmailCampaignException::notFound();
        }

        return $campaign;
    }

    /**
     * Queues one email notification per recipient in the campaign's segment and
     * marks the campaign as sent. Delivery is handled by the scheduled
     * RunNotificationsCommand.
     *
     * @throws EmailCampaignException
     */
    public function send(EmailCampaign $campaign): EmailCampaign
    {
        if ($campaign->getStatus() === EmailCampaignStatus::Sent) {
            throw EmailCampaignException::alreadySent();
        }

        $recipients = $this->resolveRecipients($campaign->getSegment());
        $sentAt = new DateTime();

        // Atomically claim the campaign (draft -> sent) so a concurrent/retried
        // send can't fan out the segment twice. Only the request that flips the
        // row queues the emails.
        if (!$this->emailCampaignRepository->markSentIfDraft($campaign, count($recipients), $sentAt)) {
            throw EmailCampaignException::alreadySent();
        }

        foreach ($recipients as $email) {
            $this->notificationService->createEmailNotification(
                [$email],
                $campaign->getSubject(),
                self::CAMPAIGN_TEMPLATE,
                [
                    'heading' => $campaign->getHeading(),
                    'body' => $campaign->getBody(),
                ],
            );
        }

        $campaign->setRecipientCount(count($recipients));
        $campaign->setStatus(EmailCampaignStatus::Sent);
        $campaign->setSentAt($sentAt);

        return $campaign;
    }

    /**
     * @return string[]
     */
    private function resolveRecipients(CampaignSegment $segment): array
    {
        $sql = match ($segment) {
            CampaignSegment::AllStudents => 'SELECT email FROM users',
            CampaignSegment::ActiveSubscribers => "SELECT DISTINCT appUser.email FROM users appUser
                INNER JOIN subscriptions sub ON sub.user_id = appUser.id WHERE sub.status = 'active'",
            CampaignSegment::NewsletterSubscribers => "SELECT email FROM newsletter_subscriptions WHERE status = 'confirmed'",
        };

        return $this->entityManager->getConnection()->executeQuery($sql)->fetchFirstColumn();
    }
}
