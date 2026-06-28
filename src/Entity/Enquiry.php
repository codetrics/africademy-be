<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\EnquiryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: EnquiryRepository::class)]
#[ORM\Table(name: 'enquiries')]
#[ORM\HasLifecycleCallbacks]
class Enquiry
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[SerializedName('full_name')]
    #[ORM\Column(length: 150)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Full name cannot be blank.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Full name is too short.', maxMessage: 'Full name is too long.')]
    private string $fullName;

    #[Expose]
    #[ORM\Column(length: 255)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'Please provide a valid email address.')]
    #[Assert\Length(max: 255, maxMessage: 'Email is too long.')]
    private string $email;

    #[Expose]
    #[ORM\Column(length: 150)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Subject cannot be blank.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Subject is too short.', maxMessage: 'Subject is too long.')]
    private string $subject;

    #[Expose]
    #[ORM\Column(type: Types::TEXT)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Message cannot be blank.')]
    #[Assert\Length(min: 2, max: 5000, minMessage: 'Message is too short.', maxMessage: 'Message is too long.')]
    private string $message;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }
}
