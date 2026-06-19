<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\SubscriptionPaymentStatus;
use App\Repository\SubscriptionPaymentRepository;
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
#[ORM\Entity(repositoryClass: SubscriptionPaymentRepository::class)]
#[ORM\Table(name: 'subscription_payments')]
#[ORM\HasLifecycleCallbacks]
class SubscriptionPayment
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Subscription $subscription;

    #[Expose]
    #[ORM\Embedded(class: Money::class)]
    #[Type("App\Entity\Money")]
    private Money $amount;

    #[ORM\Column(enumType: SubscriptionPaymentStatus::class)]
    private SubscriptionPaymentStatus $status;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $periodStart;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $periodEnd;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $attemptedAt;

    #[Exclude]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gatewayResponse = null;

    #[Exclude]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pfPaymentId = null;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->amount = new Money();
        $this->status = SubscriptionPaymentStatus::Pending;
        $this->attemptedAt = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): static
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStatus(): SubscriptionPaymentStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionPaymentStatus $status): static
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

    public function getPeriodStart(): DateTime
    {
        return $this->periodStart;
    }

    public function setPeriodStart(DateTime $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): DateTime
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(DateTime $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getAttemptedAt(): DateTime
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(DateTime $attemptedAt): static
    {
        $this->attemptedAt = $attemptedAt;
        return $this;
    }

    public function getGatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function setGatewayResponse(?string $gatewayResponse): static
    {
        $this->gatewayResponse = $gatewayResponse;
        return $this;
    }

    public function getPfPaymentId(): ?string
    {
        return $this->pfPaymentId;
    }

    public function setPfPaymentId(?string $pfPaymentId): static
    {
        $this->pfPaymentId = $pfPaymentId;
        return $this;
    }
}
