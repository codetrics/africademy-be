<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\CommunityPost;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommunityPostImageSerializationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'class' => CommunityPost::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $post = $event->getObject();

        if (!$post instanceof CommunityPost) {
            return;
        }

        $imageUrl = is_null($post->getImagePath())
            ? null
            : $this->urlGenerator->generate(
                'api_community_post_image_get',
                ['id' => (string) $post->getPublicId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

        $event->getVisitor()->visitProperty(
            new StaticPropertyMetadata('', 'image', $imageUrl),
            $imageUrl,
        );
    }
}
