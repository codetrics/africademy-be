<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\AccountType;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Hashes the password and persists the User (with its cascaded UserProfile)
     * in a single transaction. Students are active immediately; teachers receive
     * ROLE_TEACHER straight away but stay pending until an admin approves them.
     *
     * @throws Exception when the email address is already registered
     */
    public function register(User $user, string $plainPassword, AccountType $accountType): User
    {
        if (!is_null($this->userRepository->findOneByEmail($user->getEmail()))) {
            throw new Exception('This email address is already registered.');
        }

        if ($accountType === AccountType::Teacher) {
            $user->setRoles([User::ROLE_TEACHER]);
            $user->setStatus(UserStatus::PendingReview);
        } else {
            $user->setRoles([User::ROLE_STUDENT]);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->beginTransaction();
        try {
            $this->userRepository->save($user, true);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        $this->sendWelcomeEmail($user, $accountType);

        return $user;
    }

    private function sendWelcomeEmail(User $user, AccountType $accountType): void
    {
        [$subject, $template] = $accountType === AccountType::Teacher
            ? ['Your Africademy facilitator account is pending approval', 'email/welcome_facilitator.html.twig']
            : ['Welcome to Africademy', 'email/welcome_student.html.twig'];

        $this->notificationService->createEmailNotification(
            [$user->getEmail()],
            $subject,
            $template,
            [
                'first_name' => $user->getProfile()->getFirstName(),
            ],
        );
    }
}
