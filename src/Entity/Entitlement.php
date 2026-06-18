<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\EntitlementSource;
use App\Enum\EntitlementStatus;
use App\Repository\EntitlementRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: EntitlementRepository::class)]
#[ORM\Table(name: 'entitlements')]
#[ORM\UniqueConstraint(name: 'uniq_entitlement_user_course', columns: ['user_id', 'course_id'])]
#[ORM\HasLifecycleCallbacks]
class Entitlement
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\Column(enumType: EntitlementSource::class)]
    private EntitlementSource $source;

    #[ORM\Column(enumType: EntitlementStatus::class)]
    private EntitlementStatus $status;

    #[Expose]
    #[SerializedName('expires_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $expiresAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = EntitlementStatus::Active;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    #[VirtualProperty(name: 'course_id')]
    #[SerializedName('course_id')]
    #[Type("string")]
    public function getCourseId(): string
    {
        return (string) $this->course->getPublicId();
    }

    public function getSource(): EntitlementSource
    {
        return $this->source;
    }

    public function setSource(EntitlementSource $source): static
    {
        $this->source = $source;
        return $this;
    }

    #[VirtualProperty(name: 'source')]
    #[SerializedName('source')]
    #[Type("string")]
    public function getSourceValue(): string
    {
        return $this->source->value;
    }

    public function getStatus(): EntitlementStatus
    {
        return $this->status;
    }

    public function setStatus(EntitlementStatus $status): static
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

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === EntitlementStatus::Active;
    }

    public function isExpired(): bool
    {
        return !is_null($this->expiresAt) && $this->expiresAt < new DateTime();
    }
}
