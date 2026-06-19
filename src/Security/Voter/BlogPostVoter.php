<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\BlogPost;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, BlogPost>
 */
final class BlogPostVoter extends Voter
{
    public const string EDIT = 'BLOG_POST_EDIT';
    public const string DELETE = 'BLOG_POST_DELETE';
    public const string PUBLISH = 'BLOG_POST_PUBLISH';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::PUBLISH], true)
            && $subject instanceof BlogPost;
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

        /** @var BlogPost $subject */
        return $subject->getAuthor()->getId() === $user->getId();
    }
}
