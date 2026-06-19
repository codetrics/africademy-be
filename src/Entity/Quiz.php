<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\QuizRepository;
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
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quizzes')]
#[ORM\HasLifecycleCallbacks]
class Quiz
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\OneToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Course $course;

    #[Expose]
    #[ORM\Column(length: 200)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Quiz title cannot be blank.')]
    #[Assert\Length(min: 3, max: 200)]
    private string $title;

    #[Expose]
    #[SerializedName('pass_threshold_percent')]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 70])]
    #[Type("integer")]
    #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'Pass threshold must be between 1 and 100.')]
    private int $passThresholdPercent = 70;

    /**
     * @var Collection<int, Question>
     */
    #[Expose]
    #[ORM\OneToMany(mappedBy: 'quiz', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Type("ArrayCollection<App\Entity\Question>")]
    private Collection $questions;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->questions = new ArrayCollection();
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

    public function getPassThresholdPercent(): int
    {
        return $this->passThresholdPercent;
    }

    public function setPassThresholdPercent(int $passThresholdPercent): static
    {
        $this->passThresholdPercent = $passThresholdPercent;
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        $this->questions->removeElement($question);

        return $this;
    }
}
