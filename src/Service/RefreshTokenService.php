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
        $this->entityManager->createQuery(
            'DELETE FROM ' . RefreshToken::class . ' refreshToken WHERE refreshToken.username = :username',
        )
            ->setParameter('username', $user->getUserIdentifier())
            ->execute();
    }

    /**
     * Revokes a single refresh token, but only if it belongs to the user (so one
     * user cannot revoke another's session). Returns whether a token was deleted.
     */
    public function revokeForUser(User $user, string $refreshToken): bool
    {
        $deleted = $this->entityManager->createQuery(
            'DELETE FROM ' . RefreshToken::class . ' refreshToken'
            . ' WHERE refreshToken.username = :username AND refreshToken.refreshToken = :token',
        )
            ->setParameter('username', $user->getUserIdentifier())
            ->setParameter('token', $refreshToken)
            ->execute();

        return $deleted > 0;
    }
}
