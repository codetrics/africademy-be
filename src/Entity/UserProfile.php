<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserProfileRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
#[ORM\Table(name: 'user_profiles')]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[SerializedName('id')]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Type("string")]
    private Ulid $publicId;

    #[Expose]
    #[SerializedName('first_name')]
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'First name cannot be blank.')]
    #[Assert\Length(min: 1, max: 100)]
    private string $firstName;

    #[Expose]
    #[SerializedName('last_name')]
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Last name cannot be blank.')]
    #[Assert\Length(min: 1, max: 100)]
    private string $lastName;

    /**
     * Relative path to the stored avatar file under var/storage/avatars/.
     * Never exposed directly — the absolute avatar URL is added by
     * UserProfileAvatarSerializationSubscriber.
     */
    #[Exclude]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[Expose]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $bio = null;

    #[Expose]
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    #[Expose]
    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'Locale cannot be blank.')]
    #[Assert\Length(max: 10)]
    private string $locale = 'en';

    #[Expose]
    #[ORM\Column(length: 64)]
    #[Assert\NotBlank(message: 'Timezone cannot be blank.')]
    #[Assert\Timezone(message: 'Please provide a valid timezone.')]
    private string $timezone = 'Africa/Johannesburg';

    #[Expose]
    #[SerializedName('created_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $createdAt;

    #[Expose]
    #[SerializedName('updated_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $updatedAt;

    public function __construct()
    {
        $this->publicId = new Ulid();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    #[VirtualProperty(name: 'display_name')]
    #[Type("string")]
    public function getDisplayName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
