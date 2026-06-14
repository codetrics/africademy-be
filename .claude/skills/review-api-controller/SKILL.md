---
name: review-api-controller
description: Review an existing OpenApiController for compliance with project conventions. Use when the user asks to review, audit, or check an API controller. Checks auth flow, request parsing, validation, response serialization, transaction wrapping, and naming conventions.
disable-model-invocation: false
argument-hint: [ControllerFileName]
allowed-tools: Read Glob Grep Bash(ls *)
---

Review the API controller: `$ARGUMENTS`

## Live context

Locate the file:
```!
find src/Controller -name "$ARGUMENTS*" -o -name "*$ARGUMENTS*" 2>/dev/null | grep -i openapi | head -5
```

Read the controller:
```!
cat "src/Controller/$ARGUMENTS.php" 2>/dev/null || cat "src/Controller/${ARGUMENTS}OpenApiController.php" 2>/dev/null || echo "File not found — check the argument"
```

## Instructions

Read the controller content shown above, then work through each section of the checklist below. Report:
- **PASS** — rule is satisfied
- **FAIL** — rule is violated (show the offending line)
- **N/A** — rule does not apply to this controller

## Checklist

### Naming & routing
- [ ] Class name ends with `OpenApiController`
- [ ] Class extends `AbstractController`
- [ ] **No constructor** — all dependencies injected as action-method parameters, never via constructor
- [ ] Routes are under `/api/v3/`
- [ ] Route names follow `app_<resource>_open_api_<action>` pattern
- [ ] Route definitions include `requirements: ['_format' => 'json']` and `defaults: ['_format' => 'json']`
- [ ] HTTP methods are explicit using `Request::METHOD_*` constants (`methods: [Request::METHOD_GET]` etc.) — no wildcard, no plain strings
- [ ] ID route params are ULID public ids constrained with `Requirement::ULID` — not `'\d+'` (no integer-PK routes)

### Auth (every action)
- [ ] `#[IsGranted('ROLE_STUDENT')]` (or a Voter) gates the action — declarative auth, not a hand-rolled role check
- [ ] `$this->getUser()` is called before any other logic
- [ ] User narrowing uses `!$user instanceof User` — not `is_null($user)` or `!= null`
- [ ] The authenticated user is used to scope data access (records are queried/filtered by the user)
- [ ] No per-action `try/catch` around security exceptions — the `kernel.exception` subscriber normalizes them into `JsonExceptionResponse`

### Request parsing
- [ ] JSON body decoded with `json_decode($request->getContent(), true)` and guarded with `json_last_error() !== JSON_ERROR_NONE`
- [ ] Query params use `$request->query->getInt/getString()` — not `$request->get()`
- [ ] Route params use `$request->attributes->getString('id')` and resolve via `findByPublicId*` — ULID public ids, never the integer PK
- [ ] No usage of `$_GET`, `$_POST`, or `$_REQUEST`

### Validation
- [ ] Required keys validated with `Tools::checkExpectedKeys()` inside a try/catch
- [ ] Catch block returns `JsonExceptionResponse(ERROR_INVALID_REQUEST, ..., HTTP_BAD_REQUEST)`
- [ ] Entity-level constraints validated via `ValidatorInterface::validate()` with a foreach loop
- [ ] Violation loop returns `HTTP_UNPROCESSABLE_ENTITY`
- [ ] No manual field-by-field checks that duplicate what `#[Assert\*]` constraints already cover

### Responses
- [ ] All error paths return `JsonExceptionResponse` with the correct constant + HTTP status code
- [ ] All success paths serialize via `SerializerService::serialize()` then `json_decode()` the result
- [ ] `$response->setData([...])` used — data set correctly on `JsonResponse`
- [ ] No raw entity getter calls used to build response arrays manually
- [ ] REST-correct status set via `Response::HTTP_*` constants: 200 reads/updates, 201 create, 204 delete/no-body, 422 validation — no bare integers
- [ ] Responses expose the ULID public id, never the integer PK

### Writes
- [ ] Multi-entity writes wrapped in `beginTransaction()` / `commit()` / `rollback()`
- [ ] `rollback()` called in the catch block before re-throwing

### Rate limiting (if applicable)
- [ ] `RateLimiterFactoryInterface` injected and `$limiter->consume()->isAccepted()` called before business logic
- [ ] Returns `ERROR_RATE_LIMIT_EXCEEDED` + `HTTP_TOO_MANY_REQUESTS` on rejection

After running through the checklist, provide a concise summary of all FAIL items with the offending line number and a suggested fix.
