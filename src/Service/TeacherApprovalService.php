<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Exception;

class TeacherApprovalService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Activates a pending teacher account and notifies them by email.
     *
     * @throws Exception when the account is not awaiting review
     */
    public function approve(User $teacher): void
    {
        if ($teacher->getStatus() !== UserStatus::PendingReview) {
            throw new Exception('Only teacher accounts pending review can be approved.');
        }

        $teacher->setStatus(UserStatus::Active);
        $this->userRepository->save($teacher, true);

        $this->notificationService->createEmailNotification(
            [$teacher->getEmail()],
            'Your Africademy teacher account is approved',
            'email/teacher_approved.html.twig',
            [
                'first_name' => $teacher->getProfile()->getFirstName(),
            ],
        );
    }

    /**
     * Rejects a pending teacher account and notifies them by email.
     *
     * @throws Exception when the account is not awaiting review
     */
    public function reject(User $teacher, ?string $reason = null): void
    {
        if ($teacher->getStatus() !== UserStatus::PendingReview) {
            throw new Exception('Only teacher accounts pending review can be rejected.');
        }

        $teacher->setStatus(UserStatus::Rejected);
        $this->userRepository->save($teacher, true);

        $this->notificationService->createEmailNotification(
            [$teacher->getEmail()],
            'Update on your Africademy teacher application',
            'email/teacher_rejected.html.twig',
            [
                'first_name' => $teacher->getProfile()->getFirstName(),
                'reason' => $reason,
            ],
        );
    }
}
