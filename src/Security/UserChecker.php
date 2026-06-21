<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocks authentication for accounts that are not active. Facilitators stay pending
 * until an admin approves them; rejected and suspended accounts cannot log in.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        match ($user->getStatus()) {
            UserStatus::PendingReview => throw new CustomUserMessageAccountStatusException('Your account is pending review.'),
            UserStatus::Rejected => throw new CustomUserMessageAccountStatusException('Your account application was rejected.'),
            UserStatus::Suspended => throw new CustomUserMessageAccountStatusException('Your account has been suspended.'),
            UserStatus::Active => null,
        };
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
