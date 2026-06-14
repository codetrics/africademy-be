# Africademy — Backend

Africademy is a single-course online learning platform. The goal of this first
version is to **validate the business idea quickly** by shipping the smallest
product that lets a student pay for, access, and watch a course.

This repository contains the **backend API** (`africademy-be`), built with
Symfony 8.1 on PHP 8.4.

---

## Product Scope (MVP)

The platform is deliberately narrow — one course, one paid audience:

| Capability | Description |
|---|---|
| **User authentication** | Student sign-up and login. Students only — no instructor/multi-tenant roles. |
| **Course access control** | Course content is gated. Only paid users can access it. |
| **Video upload & playback** | Basic video hosting and playback. No adaptive/advanced streaming. |
| **Payment integration** | A single payment flow — either a subscription or a once-off purchase. |
| **Basic admin panel** | Upload content, create the course, and view registered users. |

Anything beyond this (multiple courses, certificates, quizzes, advanced
streaming, mobile apps, etc.) is intentionally **out of scope** for the MVP.

---

## Current State

This repo is an early-stage scaffold. What exists today:

- Symfony 8.1 application skeleton (PHP 8.4+).
- Health check endpoint: `GET /health` → `{"status":"ok"}`.
- Scheduler (`symfony/scheduler`) wired via `src/Schedule.php` — currently runs a
  daily `cache:clear --env=prod` at 03:00.
- Production logging via `symfony/monolog-bundle`.
- Apache rewrite rules shipped via `symfony/apache-pack` (`public/.htaccess`).
- Docker-based deployment to the `testing_backend` service (see below).

The product features listed under **Product Scope** are the roadmap — they are
not all implemented yet.

---

## Tech Stack

- **Language:** PHP 8.4+
- **Framework:** Symfony 8.1 (`framework-bundle`, `console`, `messenger`,
  `scheduler`, `dotenv`, `runtime`, `yaml`)
- **Logging:** Monolog (`symfony/monolog-bundle`)
- **Web server:** Apache (`symfony/apache-pack`)
- **Dependency management:** Composer (`symfony/flex`)
- **Runtime/deploy:** Docker

---

## Project Structure

```
src/
  Kernel.php                    # Application kernel
  Schedule.php                  # Scheduled tasks (symfony/scheduler)
  Controller/
    HealthController.php        # GET /health
config/
  bundles.php                   # Registered bundles
  services.yaml                 # Service container (autowire + autoconfigure)
  routes.yaml                   # Route loading (attribute-based)
  packages/                     # Per-bundle configuration
public/                         # Web root (front controller + .htaccess)
bin/console                     # Symfony CLI entry point
```

---

## Local Development

Requirements: PHP 8.4+ and Composer.

```bash
# Install dependencies
composer install

# Run the app (Symfony CLI)
symfony server:start
# or with PHP's built-in server
php -S localhost:8000 -t public

# Verify it's up
curl http://localhost:8000/health
# => {"status":"ok"}
```

### Useful commands

```bash
bin/console debug:router        # List all routes
bin/console debug:container     # Inspect services
bin/console cache:clear         # Clear the cache
```

### Scheduler

The scheduler runs as a worker that consumes the schedule defined in
`src/Schedule.php`:

```bash
bin/console messenger:consume scheduler_default
```

---

## Configuration

Application configuration is environment-based via Symfony Dotenv.

- `.env` holds non-sensitive defaults committed to the repo.
- Local overrides go in `.env.local` (never committed).
- In production, `.env.local` is generated on the server at deploy time from
  CI secrets (`APP_ENV`, `APP_SECRET`).

> Do not commit secrets. `.env.local`, `*.local`, and key/credential files are
> gitignored and must stay that way.

---

## Deployment

Deployment is automated via GitHub Actions
(`.github/workflows/docker-deploy.yml`):

1. Triggered on push to `main` (or manually via `workflow_dispatch`).
2. Rsyncs source to `/opt/docker/testing_backend/` on the codetrics-lab Docker
   host (excludes `vendor/`, `var/`, `.git/`, `tests/`, etc.).
3. Writes `.env.local` on the server from CI secrets.
4. Runs `docker compose up -d --build testing_backend` against
   `../codetrics-lab/docker/docker-compose.yml`.

The scheduler container runs `php bin/console messenger:consume scheduler_default`.

---

## Conventions

This is a strictly API-only backend: every controller returns JSON under
`/api/{version}` (`*ApiController`), with a single exception — one Swagger UI
controller that renders the OpenAPI docs via Twig + Webpack Encore
(`swagger-ui-dist` from npm).

API design follows Symfony best practices:

- **Stateless JWT auth** — the `api` firewall is `stateless: true`; every request
  is authenticated by its bearer token (no sessions/cookies).
- **Declarative authorization** — actions are gated with `#[IsGranted]`
  (Voters for record-level checks); security exceptions are normalized to the
  JSON error envelope by a `kernel.exception` subscriber.
- **REST status semantics** — `200`/`201`/`204`/`422` used correctly.
- **Native backed enums** for enumerations.
- **ULID public identifiers** — the API exposes Symfony UID (ULID) values, never
  internal auto-increment primary keys.
- **Rate limiting** — sensitive endpoints are throttled with the Symfony
  RateLimiter.

Project coding standards, controller patterns, entity/repository/service
conventions, and the plan-first workflow are documented in `.claude/CLAUDE.md`.
Read it before adding new code.

## License

Proprietary.