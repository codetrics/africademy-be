<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CommunityPost;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, CommunityPost>
 */
final class CommunityPostVoter extends Voter
{
    public const string EDIT = 'COMMUNITY_POST_EDIT';
    public const string DELETE = 'COMMUNITY_POST_DELETE';
    public const string MODERATE = 'COMMUNITY_POST_MODERATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::MODERATE], true)
            && $subject instanceof CommunityPost;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        /** @var CommunityPost $subject */
        return match ($attribute) {
            self::EDIT, self::DELETE => $subject->getAuthor()->getId() === $user->getId(),
            self::MODERATE => false,
            default => false,
        };
    }
}
