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
        private readonly WelcomeMailer $welcomeMailer,
    ) {
    }

    /**
     * Hashes the password and persists the User (with its cascaded UserProfile)
     * in a single transaction. Students are active immediately; facilitators receive
     * ROLE_FACILITATOR straight away but stay pending until an admin approves them.
     *
     * Returns null when the email is already registered — callers must respond
     * identically to a successful registration so the endpoint does not leak which
     * email addresses already have an account.
     */
    public function register(User $user, string $plainPassword, AccountType $accountType): ?User
    {
        if (!is_null($this->userRepository->findOneByEmail($user->getEmail()))) {
            return null;
        }

        if ($accountType === AccountType::Facilitator) {
            $user->setRoles([User::ROLE_FACILITATOR]);
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

        // Generic welcome for every account; the verification email follows (sent
        // by the controller). Facilitators get a separate pending-approval email
        // only after they verify their address.
        $this->welcomeMailer->sendWelcome($user);

        return $user;
    }
}
