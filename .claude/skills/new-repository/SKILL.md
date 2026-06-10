---
name: new-repository
description: Scaffold a Doctrine ServiceEntityRepository for an existing entity. Use when the user asks to create a repository, or after creating a new entity with /new-entity.
disable-model-invocation: false
argument-hint: [EntityClassName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new repository for the `$ARGUMENTS` entity.

## Live context

Existing repositories (check for duplicates):
```!
ls src/Repository/
```

Confirm the entity exists:
```!
ls src/Entity/$ARGUMENTS.php 2>/dev/null && echo "Entity found" || echo "WARNING: src/Entity/$ARGUMENTS.php not found"
```

## Instructions

1. **Check for duplicates** — if `src/Repository/$ARGUMENTSRepository.php` already exists, stop and tell the user.
2. **Confirm the entity exists** — warn if `src/Entity/$ARGUMENTS.php` is not found.
3. **File location:** `src/Repository/$ARGUMENTSRepository.php`

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] `namespace App\Repository;`
- [ ] Extend `ServiceEntityRepository` — **never** `EntityRepository` directly
- [ ] `@extends ServiceEntityRepository<$ARGUMENTS>` docblock on class
- [ ] Standard `@method` hint docblock for `find`, `findOneBy`, `findAll`, `findBy`
- [ ] Constructor: `parent::__construct($registry, $ARGUMENTS::class)` — **no property promotion**
- [ ] `save($ARGUMENTS $entity, bool $flush = false): void`
- [ ] `remove($ARGUMENTS $entity, bool $flush = false): void`
- [ ] Custom query methods use `createQueryBuilder()` with fluent chaining
- [ ] Methods returning arrays have a `/** @return $ARGUMENTS[] */` docblock

## Template

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{ClassName};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<{ClassName}>
 *
 * @method {ClassName}|null find($id, $lockMode = null, $lockVersion = null)
 * @method {ClassName}|null findOneBy(array $criteria, array $orderBy = null)
 * @method {ClassName}[]    findAll()
 * @method {ClassName}[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class {ClassName}Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, {ClassName}::class);
    }

    public function save({ClassName} $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove({ClassName} $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

Replace `{ClassName}` with `$ARGUMENTS` throughout.
