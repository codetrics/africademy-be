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
- **Two-step login with mandatory email OTP** — password is the first factor, a
  6-digit emailed code the second. A successful OTP trusts the account for **2 days**
  (subsequent logins skip the code) until a credential change.
- **Email verification required** — after login, API access is gated until the user
  verifies their email (`403 email_not_verified`); public endpoints, `GET /profile`,
  and admins are exempt.
- User profiles (separate from auth identity) with avatar upload + streamed delivery.
- Email verification (OTP), password reset, and **in-session password change**
  (`POST /profile/password`); all passwords are length- and **breach-checked**
  (Pwned Passwords k-anonymity).
- A password change/reset **revokes existing sessions** (refresh tokens) and re-arms
  the OTP step on the next login.
- **Welcome emails on signup** (students; facilitators receive a pending-approval notice).
- Audit logging (`UserLog`) and queued email notifications.
- Sign-up `account_type` (`student` / `facilitator`); facilitator accounts are created
  pending admin approval and cannot log in until approved (admin approve/reject
  queues a decision email).
- Roles: `ROLE_USER` (base, always present) plus grantable/revokeable
  `ROLE_STUDENT`, `ROLE_FACILITATOR` (authors courses) and `ROLE_ADMIN`.

**Catalogue & learning**
- Courses, lessons, and categories; paginated catalogue with search/level/category filters.
- **Public catalogue** — anonymous `GET /api/v1/public/*` exposes published courses,
  bundles, active subscription plans, and categories. Responses are serialised with a
  JMS `public` group allowlist, so only non-sensitive fields are returned to anonymous
  callers (authenticated endpoints still return the full payload).
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
- Bundles (facilitator-owned course sets) and coupons/discounts.

**Engagement & content**
- Community hub — posts (tags, image), comments, likes, trending topics, with admin moderation (hide/unhide).
- Blog with **public (unauthenticated) read access** and facilitator/admin authoring.
- Newsletter — public, rate-limited **double opt-in** (confirm-by-email) + token unsubscribe.

**Admin & operations**
- Analytics dashboard (users, revenue, MRR, subscriptions, enrollments, top courses).
- Student directory, facilitator directory with approve/reject of pending facilitators
  (each queues a decision email), and a `UserLog`-backed activity feed.
- Segmented email campaigns queued through the notification pipeline.

---

## Key flows

**Authentication** — `POST /auth/register` (with `account_type` `student` or
`facilitator`) creates the account and queues a welcome email; students are active
immediately, facilitators stay pending admin approval. The email must be verified
(`POST /auth/verify-email/request` then `/auth/verify-email`) before the API is
usable. **Login is two-step**: `POST /auth/login` (email + password) returns
`otp_pending: true` and a short-lived `pre_auth_token` while emailing a 6-digit code —
unless the account is within its 2-day OTP trust window, in which case the JWT access
token + refresh token are returned directly. `POST /auth/login/otp/verify` exchanges
the code for the tokens (`/auth/login/otp/request` resends), and `POST /auth/refresh`
rotates the access token. `POST /auth/logout` revokes refresh tokens (a `refresh_token`
logs out that device; omit it or send `all: true` to end every session) — the stateless
access token itself remains valid until it expires. Password reset is request + confirm;
logged-in users change their password at `POST /profile/password`. All passwords are
breach-checked, and a change/reset revokes other sessions. Sensitive endpoints are rate-limited.

**Authoring (facilitator)** — create a course → add lessons (and upload a video per
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
- **Pagination:** KnpPaginatorBundle (`limit` clamped to 100 on every list endpoint).
- **Docs/UI:** Swagger UI (`swagger-ui-dist`) via Twig + Webpack Encore, gated by HTTP Basic.
- **Static analysis:** PHPStan (level 5) — `vendor/bin/phpstan analyse`.
- **Web server / deploy:** Apache (`symfony/apache-pack`, `mod_xsendfile` for video),
  Docker.

---

## API & documentation

- **Swagger UI:** `GET /open-api/docs` (raw spec at `/open-api/docs.json`).
  Protected by **HTTP Basic** against a dedicated docs user (separate from API
  users). Provision the single login with `bin/console app:docs-user:create <username>`
  (prompts for the password; fails if one already exists).
- **Source of truth:** `config/openapi.yaml`.
- **Health check:** `GET /health` → `{"status":"ok"}`.

All resources are addressed by **ULID** public identifiers; internal auto-increment
keys are never exposed.

### Error handling

Every error on an `/api` request returns the JSON envelope (never an HTML error
page) — including 404s and unexpected 500s, normalized by `APIExceptionsSubscriber`:

```json
{ "error": "not_found", "error_description": "The requested resource was not found." }
```

| Status | When |
|---|---|
| `400` | malformed JSON / missing required fields |
| `401` | not authenticated — no token, or an expired/invalid token |
| `403` | authenticated but lacking the required role/ownership |
| `404` | unknown route or resource |
| `409` | conflict (e.g. duplicate) |
| `422` | validation failure |
| `429` | rate limit exceeded |
| `500` | unexpected error (logged; generic client message) |

Authentication errors are specific: `JWTExceptionSubscriber` returns distinct
401 messages for **expired**, **invalid**, and **missing** tokens, and embeds the
user's ULID `id` in the issued JWT payload. Non-`/api` paths (e.g. the Swagger UI)
keep Symfony's default handling.

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
bin/console app:user:grant-role <email> ROLE_FACILITATOR   # Grant a role (add --revoke to revoke)
bin/console app:docs-user:create <username>            # Provision the Swagger UI login
bin/console cache:clear
vendor/bin/phpstan analyse               # Static analysis (level 5)
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
