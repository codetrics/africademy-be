<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\QuizAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
#[ORM\Table(name: 'quiz_attempts')]
#[ORM\Index(name: 'idx_attempt_quiz_student', columns: ['quiz_id', 'student_id'])]
#[ORM\HasLifecycleCallbacks]
class QuizAttempt
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(name: 'quiz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[Expose]
    #[SerializedName('score_percent')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    private int $scorePercent;

    #[Expose]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Type("boolean")]
    private bool $passed;

    /**
     * Snapshot of the submitted answers as a map of question public id to the
     * chosen choice public id, kept for auditing.
     *
     * @var array<string, string>
     */
    #[Expose]
    #[ORM\Column(type: Types::JSON)]
    #[Type("array<string, string>")]
    private array $answers = [];

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getQuiz(): Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(Quiz $quiz): static
    {
        $this->quiz = $quiz;
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

    public function getScorePercent(): int
    {
        return $this->scorePercent;
    }

    public function setScorePercent(int $scorePercent): static
    {
        $this->scorePercent = $scorePercent;
        return $this;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): static
    {
        $this->passed = $passed;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @param array<string, string> $answers
     */
    public function setAnswers(array $answers): static
    {
        $this->answers = $answers;
        return $this;
    }
}
