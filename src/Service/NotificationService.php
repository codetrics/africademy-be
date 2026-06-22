<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationEmail;
use App\Enum\NotificationStatus;
use App\Repository\NotificationEmailRepository;
use DateTime;
use Throwable;

class NotificationService
{
    public const string DEFAULT_FROM = 'no-reply@africademy.co.za';

    /** A notification is retried up to this many times before it is marked failed. */
    public const int MAX_ATTEMPTS = 3;

    /** Per-attempt backoff (minutes) applied by pushing sendAt forward on failure. */
    public const int RETRY_BACKOFF_MINUTES = 5;

    public function __construct(
        private readonly NotificationEmailRepository $notificationEmailRepository,
        private readonly MailService $mailService,
    ) {
    }

    /**
     * Queues an email notification for delivery. Stored as pending; the
     * RunNotificationsCommand dispatches it once sendAt has passed.
     *
     * @param string[] $toAddresses
     * @param array<string, mixed> $context
     * @param string[] $ccAddresses
     */
    public function createEmailNotification(
        array $toAddresses,
        string $subject,
        string $template,
        array $context,
        ?DateTime $sendAt = null,
        array $ccAddresses = [],
        ?string $attachmentPath = null,
    ): NotificationEmail {
        $notification = new NotificationEmail();
        $notification->setToAddresses($toAddresses);
        $notification->setCcAddresses($ccAddresses);
        $notification->setSubject($subject);
        $notification->setTemplate($template);
        $notification->setContext($context);
        $notification->setAttachmentPath($attachmentPath);
        $notification->setSendAt($sendAt ?? new DateTime());

        $this->notificationEmailRepository->save($notification, true);

        return $notification;
    }

    public function send(NotificationEmail $notification): void
    {
        $notification->incrementAttempts();

        try {
            $this->mailService->sendMail(
                $notification->getSubject(),
                $notification->getToAddresses(),
                $notification->getCcAddresses(),
                self::DEFAULT_FROM,
                $notification->getTemplate(),
                $notification->getContext(),
                $notification->getAttachmentPath(),
            );

            $notification->setStatus(NotificationStatus::Sent);
            $notification->setSentAt(new DateTime());
            $notification->setResponse('OK');
        } catch (Throwable $exception) {
            // Transient failures (e.g. mailer briefly down) are retried: keep the
            // notification pending and back off by pushing sendAt forward, so a
            // momentary outage doesn't permanently drop the email. Give up only
            // once the attempt budget is spent.
            if ($notification->getAttempts() < self::MAX_ATTEMPTS) {
                $notification->setStatus(NotificationStatus::Pending);
                $notification->setSendAt(new DateTime(sprintf('+%d minutes', self::RETRY_BACKOFF_MINUTES * $notification->getAttempts())));
            } else {
                $notification->setStatus(NotificationStatus::Failed);
            }

            $notification->setResponse($exception->getMessage());
        }

        $this->notificationEmailRepository->save($notification, true);
    }

    /**
     * Sends all pending notifications that are due. Returns the number processed.
     */
    public function dispatchDue(int $limit = 50): int
    {
        $due = $this->notificationEmailRepository->findPendingDue(new DateTime(), $limit);

        foreach ($due as $notification) {
            $this->send($notification);
        }

        return count($due);
    }
}
