---
name: new-event-subscriber
description: Scaffold a Symfony event subscriber. Use when the user asks to listen to a Symfony kernel event, Doctrine lifecycle event, JWT event, or any event dispatched via the Symfony event system. Subscribers are auto-tagged via autoconfigure.
disable-model-invocation: false
argument-hint: [SubscriberName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new event subscriber named `$ARGUMENTSSubscriber`.

## Live context

Existing subscribers (check for duplicates and see what events are already handled):
```!
ls src/EventSubscriber/
```

## Instructions

1. **Check for duplicates** — if `src/EventSubscriber/$ARGUMENTSSubscriber.php` already exists, stop.
2. **Read an existing subscriber** for style reference — e.g. `JWTExceptionSubscriber.php` for JWT events, `OpenAPIExceptionsSubscriber.php` for API exception handling.
3. **File location:** `src/EventSubscriber/$ARGUMENTSSubscriber.php`
4. No manual service definition needed — `autoconfigure: true` in `services.yaml` auto-tags it.

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] Implement `EventSubscriberInterface`
- [ ] `getSubscribedEvents()` returns `[EventClass::class => 'methodName']` — use the event class constant, not a plain string
- [ ] Constructor: property-promoted `private readonly` dependencies
- [ ] For `KernelEvents::REQUEST` listeners: guard with `$event->isMainRequest()` to skip sub-requests
- [ ] For JWT events: import from `Lexik\Bundle\JWTAuthenticationBundle\Event\*`
- [ ] Listener methods are `public` with the typed event parameter and `: void` return type

## Template (kernel event)

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class $ARGUMENTSSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SomeDependency $dependency,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // logic here
    }
}
```

## Template (JWT events — for API auth customisation)

```php
public static function getSubscribedEvents(): array
{
    return [
        JWTCreatedEvent::class  => 'onJWTCreated',
        JWTExpiredEvent::class  => 'onJWTExpired',
        JWTNotFoundEvent::class => 'onJWTNotFound',
        JWTInvalidEvent::class  => 'onJWTInvalid',
    ];
}
```

Adjust the event map and handler methods to match the actual events the user wants to listen to.
