<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\BundleStatus;
use App\Repository\BundleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: BundleRepository::class)]
#[ORM\Table(name: 'bundles')]
#[ORM\Index(name: 'idx_bundle_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'This bundle slug is already in use.')]
class Bundle
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Bundle title cannot be blank.')]
    #[Assert\Length(min: 3, max: 200)]
    private string $title;

    // System-generated from the title by BundleService; never user-supplied.
    #[Expose]
    #[Groups(['public'])]
    #[ORM\Column(length: 220, unique: true)]
    #[Type("string")]
    private string $slug;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Type("string")]
    private ?string $description = null;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Embedded(class: Money::class)]
    #[Type("App\Entity\Money")]
    private Money $price;

    #[Expose]
    #[Groups(['public'])]
    #[SerializedName('thumbnail')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Type("string")]
    private ?string $thumbnailPath = null;

    #[ORM\Column(enumType: BundleStatus::class)]
    private BundleStatus $status;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private User $owner;

    /**
     * @var Collection<int, Course>
     */
    #[Expose]
    #[Groups(['public'])]
    #[ORM\ManyToMany(targetEntity: Course::class)]
    #[ORM\JoinTable(name: 'bundle_courses')]
    #[Type("ArrayCollection<App\Entity\Course>")]
    private Collection $courses;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = BundleStatus::Draft;
        $this->price = new Money();
        $this->courses = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
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

    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    public function setThumbnailPath(?string $thumbnailPath): static
    {
        $this->thumbnailPath = $thumbnailPath;
        return $this;
    }

    public function getStatus(): BundleStatus
    {
        return $this->status;
    }

    public function setStatus(BundleStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    #[VirtualProperty(name: 'status')]
    #[Groups(['public'])]
    #[SerializedName('status')]
    #[Type("string")]
    public function getStatusValue(): string
    {
        return $this->status->value;
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
    #[Groups(['public'])]
    #[SerializedName('instructor')]
    public function getInstructor(): array
    {
        return [
            'id' => (string) $this->owner->getPublicId(),
            'name' => $this->owner->getProfile()->getDisplayName(),
        ];
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        $this->courses->removeElement($course);

        return $this;
    }

    public function clearCourses(): static
    {
        $this->courses->clear();

        return $this;
    }
}
