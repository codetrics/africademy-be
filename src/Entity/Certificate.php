<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\CertificateRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'certificates')]
#[ORM\UniqueConstraint(name: 'uniq_certificate_enrollment', columns: ['enrollment_id'])]
#[ORM\HasLifecycleCallbacks]
class Certificate
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    // Public, URL-safe credential token used for third-party verification.
    #[Expose]
    #[SerializedName('credential_id')]
    #[ORM\Column(length: 32, unique: true)]
    #[Type("string")]
    private string $credentialId;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Enrollment::class)]
    #[ORM\JoinColumn(name: 'enrollment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Enrollment $enrollment = null;

    // Snapshots taken at issue time so a later rename never alters an issued certificate.
    #[Expose]
    #[SerializedName('student_name')]
    #[ORM\Column(length: 255)]
    #[Type("string")]
    private string $studentName;

    #[Expose]
    #[SerializedName('course_title')]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    private string $courseTitle;

    #[Expose]
    #[SerializedName('instructor_name')]
    #[ORM\Column(length: 255)]
    #[Type("string")]
    private string $instructorName;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): static
    {
        $this->credentialId = $credentialId;
        return $this;
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

    #[VirtualProperty(name: 'course_id')]
    #[SerializedName('course_id')]
    #[Type("string")]
    public function getCourseId(): string
    {
        return (string) $this->course->getPublicId();
    }

    public function getEnrollment(): ?Enrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getStudentName(): string
    {
        return $this->studentName;
    }

    public function setStudentName(string $studentName): static
    {
        $this->studentName = $studentName;
        return $this;
    }

    public function getCourseTitle(): string
    {
        return $this->courseTitle;
    }

    public function setCourseTitle(string $courseTitle): static
    {
        $this->courseTitle = $courseTitle;
        return $this;
    }

    public function getInstructorName(): string
    {
        return $this->instructorName;
    }

    public function setInstructorName(string $instructorName): static
    {
        $this->instructorName = $instructorName;
        return $this;
    }
}
