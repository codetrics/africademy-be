<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasPublicIdTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\SubscriptionInterval;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\VirtualProperty;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plans')]
#[ORM\HasLifecycleCallbacks]
class SubscriptionPlan
{
    use HasPublicIdTrait;
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Column(length: 100)]
    #[Type("string")]
    private string $name;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Column(length: 120, unique: true)]
    #[Type("string")]
    private string $slug;

    #[Expose]
    #[Groups(['public'])]
    #[ORM\Embedded(class: Money::class)]
    #[Type("App\Entity\Money")]
    private Money $price;

    #[ORM\Column(name: 'billing_interval', enumType: SubscriptionInterval::class)]
    private SubscriptionInterval $interval;

    #[Expose]
    #[Groups(['public'])]
    #[SerializedName('is_active')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Type("boolean")]
    private bool $active = true;

    public function __construct()
    {
        $this->initialisePublicId();
        $this->price = new Money();
        $this->interval = SubscriptionInterval::Monthly;
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

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function setPrice(Money $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getInterval(): SubscriptionInterval
    {
        return $this->interval;
    }

    public function setInterval(SubscriptionInterval $interval): static
    {
        $this->interval = $interval;
        return $this;
    }

    #[VirtualProperty(name: 'interval')]
    #[Groups(['public'])]
    #[SerializedName('interval')]
    #[Type("string")]
    public function getIntervalValue(): string
    {
        return $this->interval->value;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }
}
