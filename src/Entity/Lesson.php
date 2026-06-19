<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\LessonStatus;
use App\Enum\LessonType;
use App\Repository\LessonRepository;
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
#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\Table(name: 'lessons')]
#[ORM\Index(name: 'idx_lesson_course_position', columns: ['course_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class Lesson
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false)]
    private Course $course;

    #[Expose]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Lesson title cannot be blank.')]
    #[Assert\Length(min: 3, max: 200)]
    private string $title;

    #[Expose]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Type("string")]
    private ?string $body = null;

    #[ORM\Column(enumType: LessonType::class)]
    private LessonType $type;

    #[ORM\Column(enumType: LessonStatus::class)]
    private LessonStatus $status;

    #[Expose]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    #[Assert\PositiveOrZero(message: 'Position must be zero or greater.')]
    private int $position = 0;

    #[Expose]
    #[SerializedName('duration_minutes')]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Type("integer")]
    #[Assert\Positive(message: 'Duration must be a positive number of minutes.')]
    private ?int $durationMinutes = null;

    #[Expose]
    #[SerializedName('content_ref')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Type("string")]
    #[Assert\Length(max: 255)]
    private ?string $contentRef = null;

    /**
     * Relative path to the platform-hosted video under var/storage/lessons/.
     * Never exposed directly — the absolute video URL is added by
     * LessonVideoSerializationSubscriber. Distinct from contentRef, which
     * holds an external embed reference (e.g. a YouTube/Vimeo URL).
     */
    #[Exclude]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoPath = null;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->type = LessonType::Video;
        $this->status = LessonStatus::Draft;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getType(): LessonType
    {
        return $this->type;
    }

    public function setType(LessonType $type): static
    {
        $this->type = $type;
        return $this;
    }

    #[VirtualProperty(name: 'type')]
    #[SerializedName('type')]
    #[Type("string")]
    public function getTypeValue(): string
    {
        return $this->type->value;
    }

    public function getStatus(): LessonStatus
    {
        return $this->status;
    }

    public function setStatus(LessonStatus $status): static
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function getContentRef(): ?string
    {
        return $this->contentRef;
    }

    public function setContentRef(?string $contentRef): static
    {
        $this->contentRef = $contentRef;
        return $this;
    }

    public function getVideoPath(): ?string
    {
        return $this->videoPath;
    }

    public function setVideoPath(?string $videoPath): static
    {
        $this->videoPath = $videoPath;
        return $this;
    }
}
