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
    public const string PASSWORD_CHANGE = 'password_change';
    public const string PROFILE_UPDATE = 'profile_update';
    public const string PURCHASE_INITIATED = 'purchase_initiated';
    public const string PAYMENT_COMPLETED = 'payment_completed';
    public const string ORDER_CANCELLED = 'order_cancelled';
    public const string REFUND_REQUESTED = 'refund_requested';
    public const string PAYMENT_METHOD_ADDED = 'payment_method_added';
    public const string PAYMENT_METHOD_REMOVED = 'payment_method_removed';
    public const string PAYMENT_METHOD_DEFAULT = 'payment_method_default';
    public const string SUBSCRIPTION_CREATED = 'subscription_created';
    public const string SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    public const string SUBSCRIPTION_RENEWED = 'subscription_renewed';
    public const string SUBSCRIPTION_PAYMENT_FAILED = 'subscription_payment_failed';
    public const string REFUND_APPROVED = 'refund_approved';
    public const string REFUND_REJECTED = 'refund_rejected';
    public const string COUPON_REDEEMED = 'coupon_redeemed';

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
