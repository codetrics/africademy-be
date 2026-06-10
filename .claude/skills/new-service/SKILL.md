---
name: new-service
description: Scaffold a domain service class for this Symfony project. Use when the user asks to create a service, business logic class, or manager class. Follows project conventions — constructor injection with private readonly, strict types, transaction wrapping.
disable-model-invocation: false
argument-hint: [ServiceName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new service class named `$ARGUMENTSService` (or `$ARGUMENTS` if the argument already ends in "Service").

## Live context

Existing services (check for duplicates and similar patterns to follow):
```!
ls src/Service/*.php 2>/dev/null
```

## Instructions

1. **Check for duplicates** — if the service already exists, stop and tell the user.
2. **Read a similar existing service** before writing to match the exact style of the project.
3. **File location:** `src/Service/$ARGUMENTSService.php` (or `src/Service/$ARGUMENTS.php` if already suffixed)

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] `namespace App\Service;`
- [ ] All dependencies injected via constructor with `private readonly` — no public properties
- [ ] Every method has a declared return type
- [ ] Methods returning entity arrays have a `/** @return EntityName[] */` docblock
- [ ] Multi-entity writes wrapped in `beginTransaction()` / `commit()` / `rollback()`
- [ ] Throw `\Exception` (or a domain exception) for business logic errors — controllers convert these to `JsonExceptionResponse`
- [ ] Do not inject `Request` — accept only the data the service needs as plain scalars or objects

## Template

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Repository\ExampleRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

class {ServiceName}Service
{
    public function __construct(
        private readonly ExampleRepository $exampleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Example[]
     */
    public function findAllByContact(Contact $contact): array
    {
        return $this->exampleRepository->createQueryBuilder('e')
            ->where('e.contact = :contact')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getResult();
    }

    public function findByContactQuery(Contact $contact): Query
    {
        return $this->exampleRepository->createQueryBuilder('e')
            ->where('e.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery();
    }

    public function create(string $name, Contact $contact): Example
    {
        $example = new Example();
        $example->setName($name);
        $example->setCreatedAt(new DateTime('now'));

        $this->entityManager->beginTransaction();
        try {
            $this->exampleRepository->save($example, true);
            $this->entityManager->commit();
        } catch (\Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $example;
    }
}
```

Replace `{ServiceName}` with `$ARGUMENTS` and adjust properties/methods to match the actual requirements.
