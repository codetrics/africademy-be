---
name: new-entity
description: Scaffold a new Doctrine ORM entity for this Symfony project. Use when the user asks to create a new entity, model, or database table class. Follows project conventions — JMS serializer, PHP 8 attributes, no constructor property promotion, typed constants.
disable-model-invocation: false
argument-hint: [ClassName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new Doctrine entity class named `$ARGUMENTS`.

## Live context

Existing entities (check for duplicates before proceeding):
```!
ls src/Entity/
```

## Instructions

1. **Check for duplicates** — if `src/Entity/$ARGUMENTS.php` already exists, stop and tell the user.
2. **File location:** `src/Entity/$ARGUMENTS.php`
3. **Also create** the matching repository — run `/new-repository $ARGUMENTS` after this, or scaffold it now.

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] `namespace App\Entity;`
- [ ] `#[ExclusionPolicy(policy: 'all')]` on the class (JMS — exclude all by default)
- [ ] `#[ORM\Entity(repositoryClass: $ARGUMENTSRepository::class)]`
- [ ] **No constructor property promotion** — declare each property explicitly with its attributes stacked above it
- [ ] `ArrayCollection` relations initialized in `__construct()`, never lazily in getters
- [ ] Every exposed API field has `#[Expose]`
- [ ] `#[SerializedName('snake_case')]` to control the serialized key name
- [ ] DateTime fields use `#[Type("DateTime<'U'>")]` for Unix timestamp serialization
- [ ] Typed constants for status/enum values: `public const int STATUS_ACTIVE = 1;`
- [ ] Setters return `static` for fluent chaining
- [ ] Validation attributes use **named parameters**: `#[Assert\NotBlank(message: '...')]` — never deprecated array-style
- [ ] **Relation fields with a slug** — check `src/Service/Serialization/` for an existing handler before documenting the field as an object. If a `SubscribingHandlerInterface` handler exists for the related entity, it serializes as a plain string (its slug or name), not as an object. If no handler exists and one is needed, create it (see Serialization Handler section below).

## Template

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\{ClassName}Repository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: {ClassName}Repository::class)]
class {ClassName}
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Expose]
    private int $id;

    #[Expose]
    #[SerializedName('name')]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Name cannot be blank.')]
    #[Assert\Length(min: 2, max: 255)]
    private string $name;

    #[Expose]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Type("DateTime<'U'>")]
    private DateTime $createdAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
```

Replace `{ClassName}` with `$ARGUMENTS` throughout. Add, remove, or modify properties to match the actual requirements described by the user.

---

## Serialization Handler (`SubscribingHandlerInterface`)

When a relation entity (e.g. `Status`, `ProductType`) should serialize as a **plain string** (its slug or name) rather than a full object, create a custom JMS handler in `src/Service/Serialization/`.

**When to create one:** the related entity has a meaningful slug or name field and exposing the full object would be noisy — consumers just need the scalar value.

**Check first:** look in `src/Service/Serialization/` for an existing handler for that entity type before creating a new one.

### Existing handlers

| Handler | Entity | Serializes as |
|---|---|---|
| `StatusSerializeSubscriberHandler` | `Status` | `$status->getSlug()` |
| `ProductTypeSerializeSubscriberHandler` | `ProductType` | `$productType->getName()` |
| `TransactionTypeSerializeSubscriberHandler` | `TransactionType` | slug/name |
| `CmsAttributeTypeSerializeSubscriberHandler` | `CmsAttributeType` | slug/name |
| `ProductFeatureSerializeSubscriberHandler` | `ProductFeature` | slug/name |

### Template

```php
<?php

declare(strict_types=1);

namespace App\Service\Serialization;

use App\Entity\{RelatedEntity};
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonSerializationVisitor;

class {RelatedEntity}SerializeSubscriberHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods(): array
    {
        return [
            [
                'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'type' => {RelatedEntity}::class,
                'method' => 'serialize{RelatedEntity}ToJson',
            ],
        ];
    }

    public function serialize{RelatedEntity}ToJson(
        JsonSerializationVisitor $visitor,
        {RelatedEntity} ${relatedEntity},
        array $type,
        Context $context
    ): string {
        return $visitor->visitString(${relatedEntity}->getSlug(), $type);
    }
}
```

### Swagger / OpenAPI impact

A relation field whose entity has a handler must be documented as `type: string` in the swagger schema, **not** as `$ref` or an inline object. Add a `description` noting which handler controls the output, e.g.:

```yaml
status:
  type: string
  description: Status slug — serialized via StatusSerializeSubscriberHandler
  example: pending
```
