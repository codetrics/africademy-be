<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\CampaignSegment;
use App\Enum\EmailCampaignStatus;
use App\Repository\EmailCampaignRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: EmailCampaignRepository::class)]
#[ORM\Table(name: 'email_campaigns')]
#[ORM\HasLifecycleCallbacks]
class EmailCampaign
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Exclude]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[Expose]
    #[ORM\Column(length: 150)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Subject cannot be blank.')]
    #[Assert\Length(max: 150)]
    private string $subject;

    #[Expose]
    #[ORM\Column(length: 150)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Heading cannot be blank.')]
    #[Assert\Length(max: 150)]
    private string $heading;

    #[Expose]
    #[ORM\Column(type: Types::TEXT)]
    #[Type("string")]
    #[Assert\NotBlank(message: 'Body cannot be blank.')]
    private string $body;

    #[ORM\Column(enumType: CampaignSegment::class)]
    private CampaignSegment $segment;

    #[ORM\Column(enumType: EmailCampaignStatus::class)]
    private EmailCampaignStatus $status = EmailCampaignStatus::Draft;

    #[Expose]
    #[SerializedName('recipient_count')]
    #[ORM\Column]
    #[Type("integer")]
    private int $recipientCount = 0;

    #[Expose]
    #[SerializedName('sent_at')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Type("DateTime<'U'>")]
    private ?DateTime $sentAt = null;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function setHeading(string $heading): static
    {
        $this->heading = $heading;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getSegment(): CampaignSegment
    {
        return $this->segment;
    }

    public function setSegment(CampaignSegment $segment): static
    {
        $this->segment = $segment;
        return $this;
    }

    #[VirtualProperty(name: 'segment')]
    #[SerializedName('segment')]
    #[Type("string")]
    public function getSegmentValue(): string
    {
        return $this->segment->value;
    }

    public function getStatus(): EmailCampaignStatus
    {
        return $this->status;
    }

    public function setStatus(EmailCampaignStatus $status): static
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

    public function getRecipientCount(): int
    {
        return $this->recipientCount;
    }

    public function setRecipientCount(int $recipientCount): static
    {
        $this->recipientCount = $recipientCount;
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
}
