<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Bundle;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Bundle>
 */
final class BundleVoter extends Voter
{
    public const string EDIT = 'BUNDLE_EDIT';
    public const string DELETE = 'BUNDLE_DELETE';
    public const string PUBLISH = 'BUNDLE_PUBLISH';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::PUBLISH], true)
            && $subject instanceof Bundle;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Bundle $subject */
        return $subject->getOwner()->getId() === $user->getId();
    }
}
