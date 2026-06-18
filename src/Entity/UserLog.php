<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: UserLogRepository::class)]
#[ORM\Table(name: 'user_logs')]
#[ORM\Index(name: 'idx_user_log_username', columns: ['username'])]
#[ORM\HasLifecycleCallbacks]
class UserLog
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: UserLogType::class)]
    #[ORM\JoinColumn(name: 'user_log_type_id', referencedColumnName: 'id', nullable: false)]
    private UserLogType $userLogType;

    #[Expose]
    #[ORM\Column(length: 180, nullable: true)]
    #[Type("string")]
    private ?string $username = null;

    #[Expose]
    #[SerializedName('user_agent')]
    #[ORM\Column(length: 512, nullable: true)]
    #[Type("string")]
    private ?string $userAgent = null;

    #[Expose]
    #[SerializedName('ip_address')]
    #[ORM\Column(length: 45, nullable: true)]
    #[Type("string")]
    private ?string $ipAddress = null;

    #[Expose]
    #[ORM\Column(length: 255)]
    #[Type("string")]
    private string $message;

    /**
     * @var array<string, mixed>|null
     */
    #[Expose]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Type("array")]
    private ?array $context = null;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserLogType(): UserLogType
    {
        return $this->userLogType;
    }

    public function setUserLogType(UserLogType $userLogType): static
    {
        $this->userLogType = $userLogType;
        return $this;
    }

    #[VirtualProperty(name: 'type')]
    #[SerializedName('type')]
    #[Type("string")]
    public function getTypeSlug(): string
    {
        return $this->userLogType->getSlug();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function setContext(?array $context): static
    {
        $this->context = $context;
        return $this;
    }
}
