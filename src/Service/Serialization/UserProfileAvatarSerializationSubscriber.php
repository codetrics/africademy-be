<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\UserProfile;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserProfileAvatarSerializationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'class' => UserProfile::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $profile = $event->getObject();

        if (!$profile instanceof UserProfile) {
            return;
        }

        $avatarUrl = is_null($profile->getAvatarPath())
            ? null
            : $this->urlGenerator->generate('api_profile_avatar_get', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $event->getVisitor()->visitProperty(
            new StaticPropertyMetadata('', 'avatar', $avatarUrl),
            $avatarUrl,
        );
    }
}
