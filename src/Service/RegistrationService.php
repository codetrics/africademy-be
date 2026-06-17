<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
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
    ) {
    }

    /**
     * Hashes the password and persists the User (with its cascaded UserProfile)
     * in a single transaction.
     *
     * @throws Exception when the email address is already registered
     */
    public function register(User $user, string $plainPassword): User
    {
        if (!is_null($this->userRepository->findOneByEmail($user->getEmail()))) {
            throw new Exception('This email address is already registered.');
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

        return $user;
    }
}
