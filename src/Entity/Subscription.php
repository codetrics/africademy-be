<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\SubscriptionStatus;
use App\Repository\SubscriptionRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscription_user_status', columns: ['user_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\SubscriptionPlan")]
    private SubscriptionPlan $plan;

    #[Expose]
    #[SerializedName('payment_method')]
    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', referencedColumnName: 'id', nullable: false)]
    #[Type("App\Entity\PaymentMethod")]
    private PaymentMethod $paymentMethod;

    #[ORM\Column(enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status;

    #[Expose]
    #[SerializedName('current_period_start')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $currentPeriodStart;

    #[Expose]
    #[SerializedName('current_period_end')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $currentPeriodEnd;

    #[Expose]
    #[SerializedName('cancel_at_period_end')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Type("boolean")]
    private bool $cancelAtPeriodEnd = false;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = SubscriptionStatus::Active;
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

    public function getPlan(): SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(SubscriptionPlan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): static
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

    public function getCurrentPeriodStart(): DateTime
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(DateTime $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;
        return $this;
    }

    public function getCurrentPeriodEnd(): DateTime
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(DateTime $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;
        return $this;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $cancelAtPeriodEnd): static
    {
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }
}
