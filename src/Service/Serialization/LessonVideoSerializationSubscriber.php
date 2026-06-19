<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\Lesson;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LessonVideoSerializationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'class' => Lesson::class,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $lesson = $event->getObject();

        if (!$lesson instanceof Lesson) {
            return;
        }

        $videoUrl = is_null($lesson->getVideoPath())
            ? null
            : $this->urlGenerator->generate(
                'api_lesson_video_get',
                [
                    'version' => 'v1',
                    'courseId' => (string) $lesson->getCourse()->getPublicId(),
                    'id' => (string) $lesson->getPublicId(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

        $event->getVisitor()->visitProperty(
            new StaticPropertyMetadata('', 'video_url', $videoUrl),
            $videoUrl,
        );
    }
}
