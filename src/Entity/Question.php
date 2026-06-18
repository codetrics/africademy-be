<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\QuestionType;
use App\Repository\QuestionRepository;
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
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'quiz_questions')]
#[ORM\Index(name: 'idx_question_quiz_position', columns: ['quiz_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class Question
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'quiz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[Expose]
    #[ORM\Column(type: Types::TEXT)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Question text cannot be blank.')]
    private string $text;

    #[ORM\Column(enumType: QuestionType::class)]
    private QuestionType $type;

    #[Expose]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    #[Assert\PositiveOrZero(message: 'Position must be zero or greater.')]
    private int $position = 0;

    /**
     * @var Collection<int, Choice>
     */
    #[Expose]
    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Choice::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Type("ArrayCollection<App\Entity\Choice>")]
    private Collection $choices;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->type = QuestionType::SingleChoice;
        $this->choices = new ArrayCollection();
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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getType(): QuestionType
    {
        return $this->type;
    }

    public function setType(QuestionType $type): static
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return Collection<int, Choice>
     */
    public function getChoices(): Collection
    {
        return $this->choices;
    }

    public function addChoice(Choice $choice): static
    {
        if (!$this->choices->contains($choice)) {
            $this->choices->add($choice);
            $choice->setQuestion($this);
        }

        return $this;
    }

    public function removeChoice(Choice $choice): static
    {
        $this->choices->removeElement($choice);

        return $this;
    }

    /**
     * @return Choice[]
     */
    public function getCorrectChoices(): array
    {
        return $this->choices->filter(static fn (Choice $choice): bool => $choice->isCorrect())->getValues();
    }
}
