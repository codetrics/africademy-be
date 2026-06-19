<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\PaymentMethodStatus;
use App\Repository\PaymentMethodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'payment_methods')]
#[ORM\Index(name: 'idx_payment_method_user', columns: ['user_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class PaymentMethod
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * Encrypted PayFast token; never serialized.
     */
    #[Exclude]
    #[ORM\Column(type: Types::TEXT)]
    private string $token;

    #[Expose]
    #[ORM\Column(length: 40)]
    #[Type("string")]
    private string $brand;

    #[Expose]
    #[ORM\Column(length: 4)]
    #[Type("string")]
    private string $last4;

    #[Expose]
    #[SerializedName('exp_month')]
    #[ORM\Column(length: 2, nullable: true)]
    #[Type("string")]
    private ?string $expMonth = null;

    #[Expose]
    #[SerializedName('exp_year')]
    #[ORM\Column(length: 4, nullable: true)]
    #[Type("string")]
    private ?string $expYear = null;

    #[Expose]
    #[SerializedName('is_default')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Type("boolean")]
    private bool $isDefault = false;

    #[ORM\Column(enumType: PaymentMethodStatus::class)]
    private PaymentMethodStatus $status;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = PaymentMethodStatus::Active;
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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getLast4(): string
    {
        return $this->last4;
    }

    public function setLast4(string $last4): static
    {
        $this->last4 = $last4;
        return $this;
    }

    public function getExpMonth(): ?string
    {
        return $this->expMonth;
    }

    public function setExpMonth(?string $expMonth): static
    {
        $this->expMonth = $expMonth;
        return $this;
    }

    public function getExpYear(): ?string
    {
        return $this->expYear;
    }

    public function setExpYear(?string $expYear): static
    {
        $this->expYear = $expYear;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getStatus(): PaymentMethodStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentMethodStatus $status): static
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
}
