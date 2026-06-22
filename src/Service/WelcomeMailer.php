<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Queues the onboarding emails. A failure to queue must never break the signup or
 * verification flow, so each send is wrapped and logged.
 */
class WelcomeMailer
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generic welcome, sent to every account at signup.
     */
    public function sendWelcome(User $user): void
    {
        $this->queue(
            $user,
            'Welcome to Africademy',
            'email/welcome.html.twig',
        );
    }

    /**
     * Sent to a facilitator once they have verified their email, letting them know
     * their account is awaiting admin approval.
     */
    public function sendFacilitatorPendingApproval(User $user): void
    {
        $this->queue(
            $user,
            'Your Africademy facilitator account is pending approval',
            'email/facilitator_pending_approval.html.twig',
        );
    }

    private function queue(User $user, string $subject, string $template): void
    {
        try {
            $this->notificationService->createEmailNotification(
                [$user->getEmail()],
                $subject,
                $template,
                [
                    'first_name' => $user->getProfile()->getFirstName(),
                ],
            );
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('Failed to queue "%s" email for %s: %s', $subject, $user->getEmail(), $exception->getMessage()));
        }
    }
}
