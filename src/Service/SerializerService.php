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

    /**
     * @param string[] $groups when non-empty, restricts output to these JMS groups
     *                          (e.g. ['public']); empty serialises every exposed field
     */
    public function serialize(mixed $data, string $format = 'json', array $groups = []): string
    {
        $context = new SerializationContext();
        $context->setSerializeNull(true);
        $context->setAttribute('allows_root_null', true);

        if ($groups !== []) {
            $context->setGroups($groups);
        }

        return $this->serializer->serialize($data, $format, $context);
    }
}
