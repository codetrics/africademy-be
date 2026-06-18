<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\VerificationPurpose;
use App\Repository\VerificationCodeRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: VerificationCodeRepository::class)]
#[ORM\Table(name: 'verification_codes')]
#[ORM\Index(name: 'idx_verification_user_purpose', columns: ['user_id', 'purpose'])]
#[ORM\HasLifecycleCallbacks]
class VerificationCode
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(enumType: VerificationPurpose::class)]
    private VerificationPurpose $purpose;

    #[ORM\Column(length: 255)]
    private string $codeHash;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $expiresAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $usedAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPurpose(): VerificationPurpose
    {
        return $this->purpose;
    }

    public function setPurpose(VerificationPurpose $purpose): static
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): static
    {
        $this->codeHash = $codeHash;
        return $this;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getUsedAt(): ?DateTime
    {
        return $this->usedAt;
    }

    public function setUsedAt(?DateTime $usedAt): static
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTime();
    }
}
