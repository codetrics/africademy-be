<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

/**
 * Immutable money value object stored as minor units (cents) plus an ISO currency code.
 */
#[ExclusionPolicy(policy: 'all')]
#[ORM\Embeddable]
class Money
{
    public const string DEFAULT_CURRENCY = 'ZAR';

    #[Expose]
    #[SerializedName('amount_cents')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Type("integer")]
    private int $amountCents;

    #[Expose]
    #[ORM\Column(length: 3)]
    #[Type("string")]
    private string $currency;

    public function __construct(int $amountCents = 0, string $currency = self::DEFAULT_CURRENCY)
    {
        $this->amountCents = $amountCents;
        $this->currency = $currency;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function withAmountCents(int $amountCents): self
    {
        return new self($amountCents, $this->currency);
    }
}
