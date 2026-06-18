<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
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
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Order
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
    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: true)]
    #[Type("App\Entity\Course")]
    private ?Course $course = null;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: Bundle::class)]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id', nullable: true)]
    #[Type("App\Entity\Bundle")]
    private ?Bundle $bundle = null;

    #[Expose]
    #[ORM\Embedded(class: Money::class)]
    #[Type("App\Entity\Money")]
    private Money $amount;

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[Exclude]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pfPaymentId = null;

    #[Expose]
    #[SerializedName('paid_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $paidAt = null;

    #[Expose]
    #[SerializedName('refunded_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $refundedAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = OrderStatus::Pending;
        $this->amount = new Money();
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

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    public function getBundle(): ?Bundle
    {
        return $this->bundle;
    }

    public function setBundle(?Bundle $bundle): static
    {
        $this->bundle = $bundle;
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

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
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

    public function getPfPaymentId(): ?string
    {
        return $this->pfPaymentId;
    }

    public function setPfPaymentId(?string $pfPaymentId): static
    {
        $this->pfPaymentId = $pfPaymentId;
        return $this;
    }

    public function getPaidAt(): ?DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTime $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getRefundedAt(): ?DateTime
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?DateTime $refundedAt): static
    {
        $this->refundedAt = $refundedAt;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }
}
