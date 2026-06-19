<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\CommunityPostLikeRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: CommunityPostLikeRepository::class)]
#[ORM\Table(name: 'community_post_likes')]
#[ORM\UniqueConstraint(name: 'uniq_community_like_post_user', columns: ['post_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class CommunityPostLike
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: CommunityPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CommunityPost $post;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPost(): CommunityPost
    {
        return $this->post;
    }

    public function setPost(CommunityPost $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
