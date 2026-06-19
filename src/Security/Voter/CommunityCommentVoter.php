<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CommunityComment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, CommunityComment>
 */
final class CommunityCommentVoter extends Voter
{
    public const string DELETE = 'COMMUNITY_COMMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::DELETE && $subject instanceof CommunityComment;
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

        /** @var CommunityComment $subject */
        return $subject->getAuthor()->getId() === $user->getId()
            || $subject->getPost()->getAuthor()->getId() === $user->getId();
    }
}
