<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Exceptions\JsonExceptionResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns Lexik JWT authentication failures into specific JSON responses (expired
 * vs invalid vs missing), and enriches the issued token with the user's public
 * id so clients can identify the caller without an extra round-trip.
 */
class JWTExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJWTCreated',
            Events::JWT_DECODED => 'onJWTDecoded',
            Events::JWT_EXPIRED => 'onJWTExpired',
            Events::JWT_INVALID => 'onJWTInvalid',
            Events::JWT_NOT_FOUND => 'onJWTNotFound',
        ];
    }

    /**
     * Rejects pre-auth tokens (carrying otp_pending) on the API firewall so a
     * half-finished login cannot be used as a real access token.
     */
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        if (($event->getPayload()['otp_pending'] ?? false) === true) {
            $event->markAsInvalid();
        }
    }

    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse($this->unauthorized('Token expired.'));
    }

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse($this->unauthorized('Invalid token.'));
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse($this->unauthorized('Authentication token not found.'));
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['id'] = (string) $user->getPublicId();
        $event->setData($payload);
    }

    private function unauthorized(string $message): JsonExceptionResponse
    {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $message,
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
