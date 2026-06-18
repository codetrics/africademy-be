<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\DiscountType;
use App\Repository\CouponRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupons')]
#[ORM\HasLifecycleCallbacks]
class Coupon
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[ORM\Column(length: 60, unique: true)]
    #[Type("string")]
    private string $code;

    #[ORM\Column(enumType: DiscountType::class)]
    private DiscountType $discountType;

    /**
     * Percent points (0-100) for percent discounts, or minor units (cents) for fixed.
     */
    #[Expose]
    #[SerializedName('discount_value')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    private int $discountValue;

    #[Expose]
    #[SerializedName('max_redemptions')]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Type("integer")]
    private ?int $maxRedemptions = null;

    #[Expose]
    #[SerializedName('redemption_count')]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Type("integer")]
    private int $redemptionCount = 0;

    #[Expose]
    #[SerializedName('min_amount_cents')]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Type("integer")]
    private ?int $minAmountCents = null;

    #[Expose]
    #[SerializedName('expires_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $expiresAt = null;

    #[Expose]
    #[SerializedName('is_active')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Type("boolean")]
    private bool $active = true;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->discountType = DiscountType::Percent;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getDiscountType(): DiscountType
    {
        return $this->discountType;
    }

    public function setDiscountType(DiscountType $discountType): static
    {
        $this->discountType = $discountType;
        return $this;
    }

    #[VirtualProperty(name: 'discount_type')]
    #[SerializedName('discount_type')]
    #[Type("string")]
    public function getDiscountTypeValue(): string
    {
        return $this->discountType->value;
    }

    public function getDiscountValue(): int
    {
        return $this->discountValue;
    }

    public function setDiscountValue(int $discountValue): static
    {
        $this->discountValue = $discountValue;
        return $this;
    }

    public function getMaxRedemptions(): ?int
    {
        return $this->maxRedemptions;
    }

    public function setMaxRedemptions(?int $maxRedemptions): static
    {
        $this->maxRedemptions = $maxRedemptions;
        return $this;
    }

    public function getRedemptionCount(): int
    {
        return $this->redemptionCount;
    }

    public function incrementRedemptionCount(): static
    {
        $this->redemptionCount++;
        return $this;
    }

    public function getMinAmountCents(): ?int
    {
        return $this->minAmountCents;
    }

    public function setMinAmountCents(?int $minAmountCents): static
    {
        $this->minAmountCents = $minAmountCents;
        return $this;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }
}
