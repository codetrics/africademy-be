<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefundRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Queues refund outcome emails. A failure to queue must never break the admin
 * approve/reject action, so each send is wrapped and logged.
 */
class RefundMailer
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendApproved(RefundRequest $refundRequest): void
    {
        $this->queue(
            $refundRequest,
            'Your Africademy refund has been approved',
            'email/refund_approved.html.twig',
        );
    }

    public function sendRejected(RefundRequest $refundRequest): void
    {
        $this->queue(
            $refundRequest,
            'Your Africademy refund request was declined',
            'email/refund_rejected.html.twig',
        );
    }

    private function queue(RefundRequest $refundRequest, string $subject, string $template): void
    {
        $user = $refundRequest->getUser();
        $amount = $refundRequest->getOrder()->getAmount();

        try {
            $this->notificationService->createEmailNotification(
                [$user->getEmail()],
                $subject,
                $template,
                [
                    'first_name' => $user->getProfile()->getFirstName(),
                    'amount' => number_format($amount->getAmountCents() / 100, 2),
                    'currency' => $amount->getCurrency(),
                ],
            );
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('Failed to queue "%s" email for %s: %s', $subject, $user->getEmail(), $exception->getMessage()));
        }
    }
}
