<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\BlogPostStatus;
use App\Repository\BlogPostRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\Table(name: 'blog_posts')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'This blog post slug is already in use.')]
class BlogPost
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[ORM\Column(length: 280, unique: true)]
    #[Type("string")]
    private string $slug;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: BlogCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\BlogCategory")]
    #[Assert\NotNull(message: 'A blog post must belong to a category.')]
    private BlogCategory $category;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[Expose]
    #[ORM\Column(length: 255)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Title cannot be blank.')]
    #[Assert\Length(min: 3, max: 255)]
    private string $title;

    #[Expose]
    #[ORM\Column(length: 500)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Excerpt cannot be blank.')]
    #[Assert\Length(max: 500)]
    private string $excerpt;

    #[Expose]
    #[ORM\Column(type: Types::TEXT)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Body cannot be blank.')]
    private string $body;

    /**
     * Relative path to the stored cover image under var/storage/blog/.
     * Never exposed directly — the absolute cover URL is added by
     * BlogPostCoverSerializationSubscriber.
     */
    #[Exclude]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImagePath = null;

    #[Expose]
    #[SerializedName('read_time_minutes')]
    #[ORM\Column]
    #[Type("integer")]
    private int $readTimeMinutes = 1;

    #[Expose]
    #[SerializedName('is_featured')]
    #[ORM\Column]
    #[Type("boolean")]
    private bool $isFeatured = false;

    #[ORM\Column(enumType: BlogPostStatus::class)]
    private BlogPostStatus $status = BlogPostStatus::Draft;

    #[Expose]
    #[SerializedName('published_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $publishedAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCategory(): BlogCategory
    {
        return $this->category;
    }

    public function setCategory(BlogCategory $category): static
    {
        $this->category = $category;
        return $this;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): static
    {
        $this->excerpt = $excerpt;
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

    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    public function setCoverImagePath(?string $coverImagePath): static
    {
        $this->coverImagePath = $coverImagePath;
        return $this;
    }

    public function getReadTimeMinutes(): int
    {
        return $this->readTimeMinutes;
    }

    public function setReadTimeMinutes(int $readTimeMinutes): static
    {
        $this->readTimeMinutes = $readTimeMinutes;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getStatus(): BlogPostStatus
    {
        return $this->status;
    }

    public function setStatus(BlogPostStatus $status): static
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

    public function getPublishedAt(): ?DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTime $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }
}
