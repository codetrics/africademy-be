<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\EnrollmentStatus;
use App\Repository\EnrollmentRepository;
use DateTime;
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

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollments')]
#[ORM\UniqueConstraint(name: 'uniq_enrollment_student_course', columns: ['student_id', 'course_id'])]
#[ORM\HasLifecycleCallbacks]
class Enrollment
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id', nullable: false)]
    private User $student;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\Course")]
    private Course $course;

    #[ORM\Column(enumType: EnrollmentStatus::class)]
    private EnrollmentStatus $status;

    #[Expose]
    #[SerializedName('completed_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $completedAt = null;

    /**
     * @var Collection<int, LessonProgress>
     */
    #[Exclude]
    #[ORM\OneToMany(mappedBy: 'enrollment', targetEntity: LessonProgress::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lessonProgress;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = EnrollmentStatus::InProgress;
        $this->lessonProgress = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function setStudent(User $student): static
    {
        $this->student = $student;
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

    public function getStatus(): EnrollmentStatus
    {
        return $this->status;
    }

    public function setStatus(EnrollmentStatus $status): static
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

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    /**
     * @return Collection<int, LessonProgress>
     */
    public function getLessonProgress(): Collection
    {
        return $this->lessonProgress;
    }
}
