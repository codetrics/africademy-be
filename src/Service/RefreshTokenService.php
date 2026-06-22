<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Deletes every refresh token issued to the user, ending all of their
     * sessions on the next access-token expiry. Used after a password change or
     * reset so old/compromised sessions cannot be silently kept alive.
     */
    public function revokeAllForUser(User $user): void
    {
        $refreshTokens = $this->entityManager->getRepository(RefreshToken::class)
            ->findBy(['username' => $user->getUserIdentifier()]);

        foreach ($refreshTokens as $refreshToken) {
            $this->entityManager->remove($refreshToken);
        }

        $this->entityManager->flush();
    }

    /**
     * Revokes a single refresh token, but only if it belongs to the user (so one
     * user cannot revoke another's session). Returns whether a token was deleted.
     */
    public function revokeForUser(User $user, string $refreshToken): bool
    {
        $token = $this->entityManager->getRepository(RefreshToken::class)->findOneBy([
            'username' => $user->getUserIdentifier(),
            'refreshToken' => $refreshToken,
        ]);

        if (!$token instanceof RefreshToken) {
            return false;
        }

        $this->entityManager->remove($token);
        $this->entityManager->flush();

        return true;
    }
}
