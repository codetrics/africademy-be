<?php

declare(strict_types=1);

namespace App\Service;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;

class SerializerService
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function serialize(mixed $data, string $format = 'json'): string
    {
        $context = new SerializationContext();
        $context->setSerializeNull(true);
        $context->setAttribute('allows_root_null', true);

        return $this->serializer->serialize($data, $format, $context);
    }
}
