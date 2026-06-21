<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Uid\Ulid;

/**
 * Adds a ULID public identifier, exposed in the API as "id".
 * The using entity must call $this->initialisePublicId() in its constructor.
 */
trait HasPublicIdTrait
{
    #[Expose]
    #[Groups(['public'])]
    #[SerializedName('id')]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Type("string")]
    private Ulid $publicId;

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    private function initialisePublicId(): void
    {
        $this->publicId = new Ulid();
    }
}
