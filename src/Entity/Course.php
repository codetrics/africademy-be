<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\CourseLevel;
use App\Enum\CourseStatus;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'courses')]
#[ORM\Index(name: 'idx_course_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'This course slug is already in use.')]
class Course
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Course title cannot be blank.')]
    #[Assert\Length(min: 3, max: 200)]
    private string $title;

    // System-generated from the title by CourseService; never user-supplied, so not validated here.
    #[Expose]
    #[ORM\Column(length: 220, unique: true)]
    #[Type("string")]
    private string $slug;

    #[Expose]
    #[ORM\Column(length: 255, nullable: true)]
    #[Type("string")]
    #[Assert\Length(max: 255)]
    private ?string $tagline = null;

    #[Expose]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Type("string")]
    private ?string $description = null;

    #[ORM\Column(enumType: CourseStatus::class)]
    private CourseStatus $status;

    #[ORM\Column(enumType: CourseLevel::class, nullable: true)]
    private ?CourseLevel $level = null;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\Category")]
    #[Assert\NotNull(message: 'A course must belong to a category.')]
    private Category $category;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private User $owner;

    #[Expose]
    #[ORM\Embedded(class: Money::class)]
    #[Type("App\Entity\Money")]
    private Money $price;

    /**
     * @var string[]
     */
    #[Expose]
    #[ORM\Column(type: Types::JSON)]
    #[Type("array<string>")]
    private array $tags = [];

    /**
     * @var string[]
     */
    #[Expose]
    #[ORM\Column(type: Types::JSON)]
    #[Type("array<string>")]
    private array $objectives = [];

    #[Expose]
    #[SerializedName('thumbnail')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Type("string")]
    private ?string $thumbnailPath = null;

    /**
     * @var Collection<int, Lesson>
     */
    #[Exclude]
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Lesson::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lessons;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = CourseStatus::Draft;
        $this->price = new Money();
        $this->lessons = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): static
    {
        $this->tagline = $tagline;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): CourseStatus
    {
        return $this->status;
    }

    public function setStatus(CourseStatus $status): static
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

    public function getLevel(): ?CourseLevel
    {
        return $this->level;
    }

    public function setLevel(?CourseLevel $level): static
    {
        $this->level = $level;
        return $this;
    }

    #[VirtualProperty(name: 'level')]
    #[SerializedName('level')]
    #[Type("string")]
    public function getLevelValue(): ?string
    {
        return $this->level?->value;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    #[VirtualProperty(name: 'instructor')]
    #[SerializedName('instructor')]
    public function getInstructor(): array
    {
        return [
            'id' => (string) $this->owner->getPublicId(),
            'name' => $this->owner->getProfile()->getDisplayName(),
        ];
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function setPrice(Money $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function setTags(array $tags): static
    {
        $this->tags = array_values($tags);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getObjectives(): array
    {
        return $this->objectives;
    }

    /**
     * @param string[] $objectives
     */
    public function setObjectives(array $objectives): static
    {
        $this->objectives = array_values($objectives);
        return $this;
    }

    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    public function setThumbnailPath(?string $thumbnailPath): static
    {
        $this->thumbnailPath = $thumbnailPath;
        return $this;
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons->add($lesson);
            $lesson->setCourse($this);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): static
    {
        $this->lessons->removeElement($lesson);

        return $this;
    }
}
