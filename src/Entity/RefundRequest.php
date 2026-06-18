<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\RefundStatus;
use App\Repository\RefundRequestRepository;
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
#[ORM\Entity(repositoryClass: RefundRequestRepository::class)]
#[ORM\Table(name: 'refund_requests')]
#[ORM\UniqueConstraint(name: 'uniq_refund_order', columns: ['order_id'])]
#[ORM\HasLifecycleCallbacks]
class RefundRequest
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Type("App\Entity\Order")]
    private Order $order;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[Expose]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Type("string")]
    private ?string $reason = null;

    #[ORM\Column(enumType: RefundStatus::class)]
    private RefundStatus $status;

    #[Expose]
    #[SerializedName('resolved_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $resolvedAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = RefundStatus::Pending;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    #[VirtualProperty(name: 'order_id')]
    #[SerializedName('order_id')]
    #[Type("string")]
    public function getOrderId(): string
    {
        return (string) $this->order->getPublicId();
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getStatus(): RefundStatus
    {
        return $this->status;
    }

    public function setStatus(RefundStatus $status): static
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

    public function getResolvedAt(): ?DateTime
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTime $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }
}
