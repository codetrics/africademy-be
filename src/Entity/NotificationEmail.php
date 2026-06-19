<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\NotificationStatus;
use App\Repository\NotificationEmailRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: NotificationEmailRepository::class)]
#[ORM\Table(name: 'notification_emails')]
#[ORM\Index(name: 'idx_notification_status_sendat', columns: ['status', 'send_at'])]
#[ORM\HasLifecycleCallbacks]
class NotificationEmail
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $toAddresses = [];

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ccAddresses = null;

    #[Expose]
    #[ORM\Column(length: 150)]
    #[Type("string")]
    private string $subject;

    #[ORM\Column(length: 255)]
    private string $template;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(enumType: NotificationStatus::class)]
    private NotificationStatus $status;

    #[Expose]
    #[SerializedName('send_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $sendAt;

    #[Expose]
    #[SerializedName('sent_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $sentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $response = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts = 0;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->status = NotificationStatus::Pending;
        $this->sendAt = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getToAddresses(): array
    {
        return $this->toAddresses;
    }

    /**
     * @param string[] $toAddresses
     */
    public function setToAddresses(array $toAddresses): static
    {
        $this->toAddresses = array_values($toAddresses);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCcAddresses(): array
    {
        return $this->ccAddresses ?? [];
    }

    /**
     * @param string[] $ccAddresses
     */
    public function setCcAddresses(array $ccAddresses): static
    {
        $this->ccAddresses = $ccAddresses === [] ? null : array_values($ccAddresses);
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;
        return $this;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function setStatus(NotificationStatus $status): static
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

    public function getSendAt(): DateTime
    {
        return $this->sendAt;
    }

    public function setSendAt(DateTime $sendAt): static
    {
        $this->sendAt = $sendAt;
        return $this;
    }

    public function getSentAt(): ?DateTime
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTime $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): static
    {
        $this->attempts++;
        return $this;
    }
}
