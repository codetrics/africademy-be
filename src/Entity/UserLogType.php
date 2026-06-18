<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserLogTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Type;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: UserLogTypeRepository::class)]
#[ORM\Table(name: 'user_log_types')]
#[ORM\HasLifecycleCallbacks]
class UserLogType
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    public const string LOGIN = 'login';
    public const string LOGIN_FAILED = 'login_failed';
    public const string LOGOUT = 'logout';
    public const string REGISTER = 'register';
    public const string EMAIL_VERIFICATION_REQUEST = 'email_verification_request';
    public const string EMAIL_VERIFICATION = 'email_verification';
    public const string PASSWORD_RESET_REQUEST = 'password_reset_request';
    public const string PASSWORD_RESET = 'password_reset';
    public const string PROFILE_UPDATE = 'profile_update';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[ORM\Column(length: 100)]
    #[Type("string")]
    private string $name;

    #[Expose]
    #[ORM\Column(length: 120, unique: true)]
    #[Type("string")]
    private string $slug;

    public function __construct()
    {
        $this->initialisePublicId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }
}
