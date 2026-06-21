<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Exception;

class FacilitatorApprovalService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Activates a pending facilitator account and notifies them by email.
     *
     * @throws Exception when the account is not awaiting review
     */
    public function approve(User $facilitator): void
    {
        if ($facilitator->getStatus() !== UserStatus::PendingReview) {
            throw new Exception('Only facilitator accounts pending review can be approved.');
        }

        $facilitator->setStatus(UserStatus::Active);
        $this->userRepository->save($facilitator, true);

        $this->notificationService->createEmailNotification(
            [$facilitator->getEmail()],
            'Your Africademy facilitator account is approved',
            'email/facilitator_approved.html.twig',
            [
                'first_name' => $facilitator->getProfile()->getFirstName(),
            ],
        );
    }

    /**
     * Rejects a pending facilitator account and notifies them by email.
     *
     * @throws Exception when the account is not awaiting review
     */
    public function reject(User $facilitator, ?string $reason = null): void
    {
        if ($facilitator->getStatus() !== UserStatus::PendingReview) {
            throw new Exception('Only facilitator accounts pending review can be rejected.');
        }

        $facilitator->setStatus(UserStatus::Rejected);
        $this->userRepository->save($facilitator, true);

        $this->notificationService->createEmailNotification(
            [$facilitator->getEmail()],
            'Update on your Africademy facilitator application',
            'email/facilitator_rejected.html.twig',
            [
                'first_name' => $facilitator->getProfile()->getFirstName(),
                'reason' => $reason,
            ],
        );
    }
}
