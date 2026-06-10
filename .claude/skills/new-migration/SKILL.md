---
name: new-migration
description: Guide the user through generating, reviewing, and safely running a Doctrine migration after entity schema changes. Use when the user has modified entities and needs to apply those changes to the database.
disable-model-invocation: false
allowed-tools: Read Glob Grep Bash(ls *) Bash(php bin/console doctrine:migrations:diff) Bash(php bin/console doctrine:migrations:status)
---

Guide the user through creating and reviewing a Doctrine migration.

## Live context

Current migration status:
```!
php bin/console doctrine:migrations:status 2>/dev/null | head -20
```

Most recent migration file:
```!
ls -t migrations/ | head -3
```

## Step 1 — Generate the diff

Run:
```
php bin/console doctrine:migrations:diff
```

Then open the newly generated migration file in `migrations/` and share its content.

## Step 2 — Review checklist

Read the generated migration carefully before running it.

**Scope:**
- [ ] Only the tables/columns you intended are affected — no unexpected diffs from unrelated entity changes
- [ ] No Doctrine metadata tables (e.g. `doctrine_migration_versions`) are modified unexpectedly

**Data safety:**
- [ ] Adding a `NOT NULL` column to an existing populated table? → Use a two-step migration:
  1. Add the column as `NULLABLE`
  2. Backfill existing rows
  3. Add `NOT NULL` in a second migration
- [ ] Dropping a column? Confirm the column is no longer used in any query, entity, or raw SQL

**Reversibility:**
- [ ] `down()` correctly reverses everything in `up()`
- [ ] Renaming: use `RENAME COLUMN` / `ALTER TABLE ... RENAME TO` — never `DROP + ADD` (causes data loss)

**Index naming:**
- [ ] Index names follow the `unq_<table>_<field>` pattern used across this project
- [ ] No duplicate index names across tables

## Step 3 — Run locally

```
php bin/console doctrine:migrations:migrate
```

Confirm the output shows the migration applied successfully.

## Step 4 — Staging / production

**Do not run migrations on staging or production without explicit user confirmation.**
Ask the user: "Ready to run this on staging?" before proceeding.

## Data migrations for new `NotificationType` constants

Whenever a new `public const string TYPE_* = '...'` is added to `App\Entity\NotificationType`, a manual data migration **must** be created alongside it — `doctrine:migrations:diff` will not generate it automatically.

Follow this pattern (see e.g. `Version20260410130000.php`):

```php
public function up(Schema $schema): void
{
    $this->addSql(
        "INSERT INTO notification_type (name, slug, created_at, updated_at)
         VALUES ('Human Readable Name', 'the_slug_constant_value', NOW(), NOW())"
    );
}

public function down(Schema $schema): void
{
    $this->addSql(
        "DELETE FROM notification_type WHERE slug = 'the_slug_constant_value'"
    );
}
```

- The `slug` value must match the constant string exactly
- `name` should be the human-readable label (Title Case, spaces)
- Create this as a standalone migration file — do not bundle it with schema changes

## Rules

- Never use `doctrine:schema:update --force` — always use versioned migrations
- Never edit a migration that has already been executed in any environment
- If the diff is empty but you expect changes, run `php bin/console doctrine:schema:validate` to diagnose
