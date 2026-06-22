<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\CommunityPost;
use App\Entity\User;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Bundle\SecurityBundle\Security;

class CommunityPostOwnershipSerializationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
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

        $user = $this->security->getUser();
        $isAuthor = $user instanceof User && $post->getAuthor()->getId() === $user->getId();

        $event->getVisitor()->visitProperty(
            new StaticPropertyMetadata('', 'is_author', $isAuthor),
            $isAuthor,
        );
    }
}
