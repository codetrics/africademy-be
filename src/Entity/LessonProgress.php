<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\LessonProgressRepository;
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
#[ORM\Entity(repositoryClass: LessonProgressRepository::class)]
#[ORM\Table(name: 'lesson_progress')]
#[ORM\UniqueConstraint(name: 'uniq_progress_enrollment_lesson', columns: ['enrollment_id', 'lesson_id'])]
#[ORM\HasLifecycleCallbacks]
class LessonProgress
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Enrollment::class, inversedBy: 'lessonProgress')]
    #[ORM\JoinColumn(name: 'enrollment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Enrollment $enrollment;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(name: 'lesson_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[Expose]
    #[SerializedName('completed_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $completedAt;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->completedAt = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    public function setLesson(Lesson $lesson): static
    {
        $this->lesson = $lesson;
        return $this;
    }

    #[VirtualProperty(name: 'lesson_id')]
    #[SerializedName('lesson_id')]
    #[Type("string")]
    public function getLessonId(): string
    {
        return (string) $this->lesson->getPublicId();
    }

    public function getCompletedAt(): DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
