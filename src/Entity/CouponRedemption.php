<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\CouponRedemptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: CouponRedemptionRepository::class)]
#[ORM\Table(name: 'coupon_redemptions')]
#[ORM\UniqueConstraint(name: 'uniq_coupon_user', columns: ['coupon_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class CouponRedemption
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Coupon::class)]
    #[ORM\JoinColumn(name: 'coupon_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Coupon $coupon;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Subscription $subscription = null;

    #[Expose]
    #[SerializedName('amount_discounted_cents')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    private int $amountDiscountedCents;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCoupon(): Coupon
    {
        return $this->coupon;
    }

    public function setCoupon(Coupon $coupon): static
    {
        $this->coupon = $coupon;
        return $this;
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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function getAmountDiscountedCents(): int
    {
        return $this->amountDiscountedCents;
    }

    public function setAmountDiscountedCents(int $amountDiscountedCents): static
    {
        $this->amountDiscountedCents = $amountDiscountedCents;
        return $this;
    }
}
