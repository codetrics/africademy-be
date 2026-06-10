---
name: review-entity
description: Review an existing Doctrine entity for compliance with project conventions. Use when the user asks to review, audit, or check an entity class. Checks JMS serializer setup, ORM attributes, validation constraints, property declarations, and PHP 8 standards.
disable-model-invocation: false
argument-hint: [EntityClassName]
allowed-tools: Read Glob Grep Bash(ls *)
---

Review the entity: `$ARGUMENTS`

## Live context

Locate the file:
```!
find src/Entity -name "$ARGUMENTS.php" -o -name "*$ARGUMENTS*.php" 2>/dev/null | head -5
```

Read the entity:
```!
cat "src/Entity/$ARGUMENTS.php" 2>/dev/null || echo "File not found — check the argument"
```

## Instructions

Read the entity shown above, then work through each section of the checklist below. Report:
- **PASS** — rule is satisfied
- **FAIL** — rule is violated (show the offending line number and content)
- **N/A** — rule does not apply to this entity

## Checklist

### File structure
- [ ] `declare(strict_types=1)` present at the top
- [ ] `namespace App\Entity;`

### ORM mapping
- [ ] `#[ORM\Entity(repositoryClass: ...)]` present
- [ ] All ORM mapping uses PHP 8 attributes — no `/** @ORM\... */` annotations
- [ ] `#[ORM\Column]` has explicit `type:` or length where appropriate
- [ ] Unique constraints use `#[ORM\UniqueConstraint]` on the class, not on the column

### JMS serializer
- [ ] `#[ExclusionPolicy(policy: 'all')]` on the class
- [ ] Every field exposed to the API has `#[Expose]`
- [ ] No fields have `#[Exclude]` — the exclusion policy makes this redundant
- [ ] `#[SerializedName('snake_case')]` used to control serialized key names
- [ ] DateTime fields have `#[Type("DateTime<'U'>")]` for Unix timestamp output
- [ ] For every `#[Expose]`d relation field: check `src/Service/Serialization/` for a `SubscribingHandlerInterface` handler. If one exists for the related entity, that relation serializes as a plain string (slug/name), not as an object — document accordingly in swagger and do **not** treat it as a nested object

### Property declarations
- [ ] **No constructor property promotion** — every property declared explicitly above its `#[ORM\*]` attributes
- [ ] `ArrayCollection` relations initialized in `__construct()`, not lazily in getters
- [ ] No uninitialized nullable properties that should default to `null`

### Validation
- [ ] `#[Assert\*]` constraints use **named parameters**: `#[Assert\NotBlank(message: '...')]`
- [ ] No deprecated array-style constraint options: `#[Assert\NotBlank(['message' => '...'])]`

### PHP 8+ standards
- [ ] Typed constants: `public const int STATUS_ACTIVE = 1;` — no untyped `public const STATUS_ACTIVE = 1;`
- [ ] Setters return `static` for fluent chaining
- [ ] Every method has a declared return type

After running through the checklist, provide a concise summary of all FAIL items with line numbers and suggested fixes.
