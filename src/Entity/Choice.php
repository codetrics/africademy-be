<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ChoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: ChoiceRepository::class)]
#[ORM\Table(name: 'quiz_choices')]
#[ORM\Index(name: 'idx_choice_question_position', columns: ['question_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class Choice
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'choices')]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[Expose]
    #[ORM\Column(length: 500)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Choice text cannot be blank.')]
    #[Assert\Length(max: 500)]
    private string $text;

    // Never serialized — exposing this would reveal the answer key to students.
    #[Exclude]
    #[ORM\Column(name: 'is_correct', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isCorrect = false;

    #[Expose]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    #[Assert\PositiveOrZero(message: 'Position must be zero or greater.')]
    private int $position = 0;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): static
    {
        $this->question = $question;
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

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
        return $this;
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
}
