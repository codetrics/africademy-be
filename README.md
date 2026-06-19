# Africademy — Backend

Africademy is an online learning platform. This repository is the **backend API**
(`africademy-be`) — a strictly JSON, stateless-JWT API built with **Symfony 8.x on
PHP 8.4+**. It powers a multi-course catalogue with paid access, a learning
experience (lessons, video, progress, quizzes, certificates), community and
content surfaces, and an admin/operations layer.

It is **API-only**: every controller returns JSON under `/api/{version}`. The one
exception is a Swagger UI page that renders the OpenAPI spec.

---

## Features

**Accounts & identity**
- JWT authentication with refresh tokens (stateless `api` firewall).
- User profiles (separate from auth identity) with avatar upload + streamed delivery.
- Email verification (OTP) and password reset, rate-limited.
- Audit logging (`UserLog`) and queued email notifications.
- Roles: `ROLE_STUDENT` (default), `ROLE_TEACHER` (authors courses), `ROLE_ADMIN`.

**Catalogue & learning**
- Courses, lessons, and categories; paginated catalogue with search/level/category filters.
- Enrollment and per-lesson progress tracking.
- **Lesson video** — platform-hosted upload with access-gated, range-enabled
  streaming (basic hosting; no adaptive/HLS). Lessons may instead reference an
  external embed (`content_ref`).
- Quizzes (questions/choices/attempts) and completion **certificates** (PDF
  rendered from Twig via Browsershot/Chromium), with a public verification endpoint.
- Reviews & ratings with cached per-course rating/enrollment stats.

**Commerce (PayFast)**
- Entitlement-based access via a single gate fed by four paths: **free**,
  **per-course purchase**, **bundle**, and **subscription**.
- Orders + signed one-off checkout + ITN webhook (signature/amount validated);
  30-day refund requests with admin approval.
- Subscriptions + saved payment methods (libsodium-encrypted PayFast tokens,
  multiple per user) + scheduled recurring billing.
- Bundles (teacher-owned course sets) and coupons/discounts.

**Engagement & content**
- Community hub — posts (tags, image), comments, likes, trending topics.
- Blog with **public (unauthenticated) read access** and teacher/admin authoring.
- Newsletter — public, rate-limited subscribe + token unsubscribe.

**Admin & operations**
- Analytics dashboard (users, revenue, MRR, subscriptions, enrollments, top courses).
- Student directory and a `UserLog`-backed activity feed.
- Segmented email campaigns queued through the notification pipeline.

---

## Key flows

**Authentication** — `POST /auth/register` creates a user + profile (no token) →
`POST /auth/verify-email/request` then `/auth/verify-email` (OTP) → `POST /auth/login`
returns a JWT access token + refresh token → `POST /auth/refresh` exchanges the
refresh token for a new access token. Password reset is request + confirm; sensitive
endpoints are rate-limited.

**Authoring (teacher)** — create a course → add lessons (and upload a video per
lesson) → publish. Course/lesson edits and video upload are gated to the owner (or
admin) via voters.

**Access & purchase** — access to a course is decided by `AccessService`:
- **Free** course → immediate access.
- **Per-course / bundle** → `POST` purchase returns a signed PayFast checkout; PayFast
  posts back to `/webhooks/payfast/notify` (ITN, signature + amount validated) → the
  order is marked paid → an entitlement is granted and the student auto-enrolled.
- **Subscription** → a stored, tokenized card is charged; access is granted to all
  included courses while the subscription is active.

**Learning** — enrolled students call `GET /courses/{id}/learn` (gated by
`AccessService`) for lessons + progress; stream a lesson video at
`…/lessons/{id}/video` (owner, or entitled student of a published lesson); mark
lessons complete. On finishing a certificate-enabled course (passing its quiz where
required), a certificate is issued and can be verified publicly.

**Refunds** — a student opens a refund request (30-day window); an admin approves it,
which revokes the entitlement and triggers the PayFast refund.

**Recurring billing** — a scheduled command renews due subscriptions (new ledger
entry) or expires cancelled ones.

---

## Tech stack

- **Language / framework:** PHP 8.4+, Symfony 8.x (`framework-bundle`, `console`,
  `messenger`, `scheduler`, `dotenv`, `runtime`).
- **Persistence:** Doctrine ORM + Migrations (MySQL/MariaDB).
- **Auth:** Lexik JWT (`lexik/jwt-authentication-bundle`) + Gesdinet refresh tokens;
  Symfony Security, RateLimiter, UID (ULID).
- **Serialization / validation:** JMS Serializer, Symfony Validator.
- **Email:** Symfony Mailer (Twig templates), dispatched via the scheduler.
- **Payments:** PayFast (HTTP Client for live charge/refund; libsodium token encryption).
- **PDF:** Spatie Browsershot (system Chromium) for certificates.
- **Pagination:** KnpPaginatorBundle.
- **Docs/UI:** Swagger UI (`swagger-ui-dist`) via Twig + Webpack Encore.
- **Web server / deploy:** Apache (`symfony/apache-pack`, `mod_xsendfile` for video),
  Docker.

---

## API & documentation

- **Swagger UI:** `GET /open-api/docs` (raw spec at `/open-api/docs.json`).
- **Source of truth:** `config/openapi.yaml`.
- **Health check:** `GET /health` → `{"status":"ok"}`.

All resources are addressed by **ULID** public identifiers; internal auto-increment
keys are never exposed. Errors use a consistent JSON envelope; security exceptions are
normalized by a `kernel.exception` subscriber.

---

## Project structure

```
src/
  Kernel.php
  Schedule.php                # Scheduled commands (symfony/scheduler)
  Controller/                 # *ApiController (JSON) + HealthController + SwaggerUiController
  Entity/                     # Doctrine entities (ULID public ids, JMS-exposed)
  Repository/                 # ServiceEntityRepository query objects
  Service/                    # Domain services (access, payments, certificates, …)
    Serialization/            # JMS post-serialize subscribers (avatar/video/etc. URLs)
  Security/Voter/             # Record-level authorization
  Enum/                       # Native backed enums
  Exceptions/                 # Domain exceptions + JSON error envelope
config/
  openapi.yaml                # OpenAPI spec served by Swagger UI
  packages/                   # Per-bundle config (security, doctrine, payfast, …)
migrations/                   # Doctrine migrations
public/                       # Web root (front controller + .htaccess)
templates/                    # Email + certificate Twig + Swagger UI
```

---

## Local development

Requirements: PHP 8.4+, Composer, a MySQL/MariaDB database, and Node (for Swagger UI
assets). Certificate rendering needs Chromium available to Browsershot.

```bash
# 1. Install dependencies
composer install
npm install && npm run build          # Swagger UI assets (Webpack Encore)

# 2. Configure (see Configuration below) — set DATABASE_URL etc. in .env.local

# 3. Database
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate

# 4. JWT keypair (Lexik)
bin/console lexik:jwt:generate-keypair

# 5. Run
symfony server:start            # or: php -S localhost:8000 -t public public/index.php

# 6. Verify
curl http://localhost:8000/health        # => {"status":"ok"}
# Docs: http://localhost:8000/open-api/docs
```

### Useful commands

```bash
bin/console debug:router                 # List routes
bin/console doctrine:migrations:migrate  # Apply migrations
bin/console app:user:grant-role <email> ROLE_TEACHER   # Grant a role
bin/console cache:clear
```

---

## Configuration

Configuration is environment-based via Symfony Dotenv.

- `.env` holds non-sensitive **placeholder** defaults committed to the repo.
- Local overrides go in `.env.local` (never committed). In production the env is
  compiled to **`.env.local.php`** at build/deploy time (`composer dump-env prod`);
  real secrets are injected from CI, never committed.

Required / notable variables:

| Variable | Purpose |
|---|---|
| `DATABASE_URL` | Doctrine DB connection |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | Lexik JWT keypair |
| `APP_SECRET`, `APP_ENV`, `APP_BASE_URL` | Symfony core / absolute URL generation |
| `MAILER_DSN` | Outbound email (Symfony Mailer) |
| `APP_SODIUM_AEAD_KEY` | Encryption key for stored PayFast card tokens |
| `PAYFAST_MERCHANT_ID` / `_MERCHANT_KEY` / `_PASSPHRASE` | PayFast credentials |
| `PAYFAST_SANDBOX` | `true` simulates charges/refunds; `false` calls the live API |
| `PAYFAST_RETURN_URL` / `_CANCEL_URL` / `_NOTIFY_URL` | PayFast redirect + ITN URLs |
| `BROWSER_SHOT_NODE_BINARY` / `_NPM_BINARY`, `PUPPETEER_EXECUTABLE_PATH` | Certificate PDF rendering |

> Do not commit secrets. `.env.local`, `*.local`, `.env.local.php`, and
> key/credential files are gitignored and must stay that way.

---

## Scheduler

The scheduler runs as a worker consuming the schedule in `src/Schedule.php`:

```bash
bin/console messenger:consume scheduler_default
```

Scheduled commands: daily `cache:clear --env=prod`, `app:notifications:run`
(dispatch due emails), and `app:subscriptions:bill` (renew/expire subscriptions).

---

## Deployment

Automated via GitHub Actions (`.github/workflows/docker-deploy.yml`):

1. Triggered on push to `main` (or manual `workflow_dispatch`).
2. Rsyncs source to `/opt/docker/testing_backend/` on the codetrics-lab Docker host
   (excludes `vendor/`, `var/`, `.git/`, etc.).
3. Injects environment from CI secrets.
4. Runs `docker compose up -d --build testing_backend testing_scheduler` against
   `../codetrics-lab/docker/docker-compose.yml`.

The scheduler container runs `php bin/console messenger:consume scheduler_default`.
The web image is `php:8.5-apache` with `mod_xsendfile` enabled for offloaded video
streaming.

---

## Conventions

Coding standards, controller/entity/repository/service patterns, the API design
rules (stateless JWT, declarative `#[IsGranted]` authorization, REST status
semantics, native backed enums, ULID identifiers, rate limiting), and the
plan-first workflow are documented in `.claude/CLAUDE.md`. Read it before adding code.

## License

Proprietary.
