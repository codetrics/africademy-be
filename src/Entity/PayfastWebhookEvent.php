<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PayfastWebhookOutcome;
use App\Repository\PayfastWebhookEventRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use Symfony\Component\Uid\Ulid;

/**
 * An audit record of a signature-valid PayFast ITN. The raw payload is stored
 * with the recurring `token` redacted; invalid-signature ITNs are never stored
 * (they are logged to the payfast channel only). Holds buyer PII — pruned on a
 * retention schedule.
 */
#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: PayfastWebhookEventRepository::class)]
#[ORM\Table(name: 'payfast_webhook_event')]
#[ORM\Index(name: 'idx_payfast_webhook_received_at', columns: ['received_at'])]
#[ORM\Index(name: 'idx_payfast_webhook_m_payment_id', columns: ['m_payment_id'])]
class PayfastWebhookEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $publicId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $receivedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $mPaymentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pfPaymentId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentStatus = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountGrossCents = null;

    #[ORM\Column(enumType: PayfastWebhookOutcome::class)]
    private PayfastWebhookOutcome $outcome;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    public function __construct()
    {
        $this->publicId = new Ulid();
        $this->receivedAt = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    public function getReceivedAt(): DateTime
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(DateTime $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getMPaymentId(): ?string
    {
        return $this->mPaymentId;
    }

    public function setMPaymentId(?string $mPaymentId): static
    {
        $this->mPaymentId = $mPaymentId;

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

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getAmountGrossCents(): ?int
    {
        return $this->amountGrossCents;
    }

    public function setAmountGrossCents(?int $amountGrossCents): static
    {
        $this->amountGrossCents = $amountGrossCents;

        return $this;
    }

    public function getOutcome(): PayfastWebhookOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(PayfastWebhookOutcome $outcome): static
    {
        $this->outcome = $outcome;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }
}
