<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\BlogPost;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BlogPostCoverSerializationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'class' => BlogPost::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $post = $event->getObject();

        if (!$post instanceof BlogPost) {
            return;
        }

        $coverUrl = is_null($post->getCoverImagePath())
            ? null
            : $this->urlGenerator->generate(
                'api_blog_post_cover_get',
                ['id' => (string) $post->getPublicId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

        $event->getVisitor()->visitProperty(
            new StaticPropertyMetadata('', 'cover_image', $coverUrl),
            $coverUrl,
        );
    }
}
