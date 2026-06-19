<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\CommunityPostStatus;
use App\Enum\CommunityPostTag;
use App\Repository\CommunityPostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: CommunityPostRepository::class)]
#[ORM\Table(name: 'community_posts')]
#[ORM\HasLifecycleCallbacks]
class CommunityPost
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(enumType: CommunityPostTag::class)]
    private CommunityPostTag $tag;

    #[Expose]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Title cannot be blank.')]
    #[Assert\Length(min: 3, max: 200)]
    private string $title;

    #[Expose]
    #[ORM\Column(type: Types::TEXT)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Body cannot be blank.')]
    #[Assert\Length(max: 10000)]
    private string $body;

    /**
     * Relative path to the stored image under var/storage/community/.
     * Never exposed directly — the absolute image URL is added by
     * CommunityPostImageSerializationSubscriber.
     */
    #[Exclude]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[Expose]
    #[SerializedName('link_url')]
    #[ORM\Column(length: 1024, nullable: true)]
    #[Type("string")]
    #[Assert\Length(max: 1024)]
    #[Assert\Url(message: 'The link must be a valid URL.')]
    private ?string $linkUrl = null;

    #[Expose]
    #[SerializedName('like_count')]
    #[ORM\Column]
    #[Type("integer")]
    private int $likeCount = 0;

    #[Expose]
    #[SerializedName('comment_count')]
    #[ORM\Column]
    #[Type("integer")]
    private int $commentCount = 0;

    #[ORM\Column(enumType: CommunityPostStatus::class)]
    private CommunityPostStatus $status = CommunityPostStatus::Published;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;
        return $this;
    }

    #[VirtualProperty(name: 'author')]
    #[SerializedName('author')]
    #[Type("string")]
    public function getAuthorName(): string
    {
        return $this->author->getProfile()->getDisplayName();
    }

    public function getTag(): CommunityPostTag
    {
        return $this->tag;
    }

    public function setTag(CommunityPostTag $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    #[VirtualProperty(name: 'tag')]
    #[SerializedName('tag')]
    #[Type("string")]
    public function getTagValue(): string
    {
        return $this->tag->value;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): static
    {
        $this->linkUrl = $linkUrl;
        return $this;
    }

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): static
    {
        $this->likeCount = $likeCount;
        return $this;
    }

    public function getCommentCount(): int
    {
        return $this->commentCount;
    }

    public function setCommentCount(int $commentCount): static
    {
        $this->commentCount = $commentCount;
        return $this;
    }

    public function getStatus(): CommunityPostStatus
    {
        return $this->status;
    }

    public function setStatus(CommunityPostStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    #[VirtualProperty(name: 'status')]
    #[SerializedName('status')]
    #[Type("string")]
    public function getStatusValue(): string
    {
        return $this->status->value;
    }
}
