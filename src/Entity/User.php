<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'This email address is already registered.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_DEFAULT = 'ROLE_USER';
    public const string ROLE_STUDENT = 'ROLE_STUDENT';
    public const string ROLE_FACILITATOR = 'ROLE_FACILITATOR';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

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
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'Please provide a valid email address.')]
    #[Assert\Length(max: 180)]
    private string $email;

    /**
     * @var string The hashed password
     */
    #[Exclude]
    #[ORM\Column]
    private string $password;

    /**
     * @var string[]
     */
    #[Expose]
    #[Accessor(getter: 'getRoles')]
    #[ORM\Column]
    #[Type("array<string>")]
    private array $roles = [];

    #[ORM\Column(enumType: UserStatus::class)]
    private UserStatus $status;

    #[Expose]
    #[Assert\Valid]
    #[ORM\OneToOne(targetEntity: UserProfile::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\UserProfile")]
    private UserProfile $profile;

    #[Expose]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $createdAt;

    #[Expose]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $updatedAt;

    #[Expose]
    #[SerializedName('email_verified_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $emailVerifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastOtpAt = null;

    public function __construct()
    {
        $this->publicId = new Ulid();
        $this->status = UserStatus::Active;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_DEFAULT;

        return array_unique($roles);
    }

    /**
     * The roles persisted on this user, without the always-on base role that
     * getRoles() appends. Use this when granting or revoking roles.
     *
     * @return string[]
     */
    public function getRawRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    #[VirtualProperty(name: 'status')]
    #[SerializedName('status')]
    #[Type("string")]
    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function setProfile(UserProfile $profile): static
    {
        $this->profile = $profile;
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

    public function getEmailVerifiedAt(): ?DateTime
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTime $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    #[VirtualProperty(name: 'email_verified')]
    #[SerializedName('email_verified')]
    #[Type("boolean")]
    public function isEmailVerified(): bool
    {
        return !is_null($this->emailVerifiedAt);
    }

    public function getLastOtpAt(): ?DateTime
    {
        return $this->lastOtpAt;
    }

    public function setLastOtpAt(?DateTime $lastOtpAt): static
    {
        $this->lastOtpAt = $lastOtpAt;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
    }
}
