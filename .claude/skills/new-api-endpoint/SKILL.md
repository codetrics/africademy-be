---
name: new-api-endpoint
description: Add a new JSON API endpoint to an existing ApiController, or scaffold a new one. Use when the user asks to add an API route, REST endpoint, or client-facing API action. All API endpoints in this project live under /api/{version}/, use JWT auth via User, and return JsonResponse.
disable-model-invocation: false
argument-hint: [ResourceName]
allowed-tools: Read Write Edit Glob Grep Bash(ls *)
---

Scaffold a new API endpoint for the `$ARGUMENTS` resource.

## Live context

Existing API controllers:
```!
ls src/Controller/ | grep ApiController
```

Check if a controller already exists for this resource:
```!
ls src/Controller/$ARGUMENTSApiController.php 2>/dev/null && echo "Controller exists — add endpoint to it" || echo "New controller needed"
```

Existing services (to find or plan the backing service):
```!
ls src/Service/*.php 2>/dev/null
```

## Instructions

1. **Check if an `$ARGUMENTSApiController` already exists** — if so, add the new action to it rather than creating a new file.
2. **Read the existing controller first** (if it exists) to match its exact import list and code style.
3. **Read an existing `*ApiController`** (if one exists) as a reference for the pattern; otherwise follow the templates below.
4. Determine which HTTP method(s) the user wants — default to GET if unspecified.
5. **No constructor** — all dependencies are injected as action-method parameters (autowired). Never add a constructor to an `ApiController`.

## Mandatory action order (every endpoint, no exceptions)

1. Authorization gate — `#[IsGranted('ROLE_STUDENT')]` attribute on the action
2. User narrowing (narrow the authenticated user for type-safety and data scoping)
3. Request parsing
4. Key/input validation
5. Business logic (service calls)
6. Response serialization

## Authorization — declarative gate first

Gate every action with the `#[IsGranted]` attribute — this is the auth gate. The
firewall (stateless JWT) and `#[IsGranted]` reject unauthenticated/unauthorized
requests, and the `kernel.exception` subscriber renders those security
exceptions into the `JsonExceptionResponse` envelope. Use a Voter for
record-level ownership checks.

```php
#[IsGranted('ROLE_STUDENT')]
```

Then narrow the authenticated user inside the action and use it to scope data
access. The `instanceof` narrowing stays for type-safety (and as a defensive
net) — it is **not** the auth gate:

```php
$user = $this->getUser();
if (!$user instanceof User) {
    $exception = $this->createAccessDeniedException();
    return new JsonExceptionResponse(
        JsonExceptionResponse::ERROR_UNAUTHORIZED,
        $exception->getMessage(),
        Response::HTTP_UNAUTHORIZED
    );
}
```

## Request parsing rules

- JSON body: `json_decode($request->getContent(), true)` + `json_last_error() !== JSON_ERROR_NONE` guard
- Query params: `$request->query->getInt('key', default)` / `$request->query->getString('key')`
- Route params: `$request->attributes->getString('id')` — public ids are ULIDs, look up via `findByPublicId*`. Constrain the route with `requirements: ['id' => Requirement::ULID]`. Never expose or accept the integer PK.
- Never use `$_GET`, `$_POST`, or `$request->get()`

## Validation rules

- Required keys: `Tools::checkExpectedKeys($keys, $data)` inside try/catch → `ERROR_INVALID_REQUEST` on catch
- Entity constraints: `ValidatorInterface::validate()` loop → `ERROR_VALIDATION` + `HTTP_UNPROCESSABLE_ENTITY`

## Error response reference

| Situation | Constant | Status |
|---|---|---|
| Not authenticated | `ERROR_UNAUTHORIZED` | `HTTP_UNAUTHORIZED` |
| Bad/missing JSON | `ERROR_INVALID_JSON` | `HTTP_BAD_REQUEST` |
| Missing required keys | `ERROR_INVALID_REQUEST` | `HTTP_BAD_REQUEST` |
| Constraint violations | `ERROR_VALIDATION` | `HTTP_UNPROCESSABLE_ENTITY` |
| Resource not found | `ERROR_NOT_FOUND` | `HTTP_NOT_FOUND` |
| Rate limited | `ERROR_RATE_LIMIT_EXCEEDED` | `HTTP_TOO_MANY_REQUESTS` |
| Unexpected error | `ERROR_INTERNAL_SERVER_ERROR` | `HTTP_INTERNAL_SERVER_ERROR` |

## GET list with pagination

```php
#[Route(
    '/api/{version}/{resource}',
    name: 'app_{resource}_api_get',
    requirements: ['_format' => 'json'],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_GET],
)]
#[IsGranted('ROLE_STUDENT')]
public function get{Resource}s(
    Request $request,
    {Resource}Service ${resource}Service,
    PaginatorInterface $paginator,
    SerializerService $serializerService,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    $page = $request->query->getInt('page', 1);
    $limit = $request->query->getInt('limit', 10);

    $query = ${resource}Service->findByUserQuery($user);
    $paginated = $paginator->paginate($query, $page, $limit);

    $dataJSON = $serializerService->serialize($paginated->getItems());
    $paginationJSON = $serializerService->serialize(new PaginationMeta($paginated));

    $response = new JsonResponse();
    $response->setData([
        '{resource}s' => json_decode($dataJSON),
        'pagination' => json_decode($paginationJSON),
    ]);
    return $response;
}
```

## POST create

```php
#[Route(
    '/api/{version}/{resource}',
    name: 'app_{resource}_api_post',
    requirements: ['_format' => 'json'],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_POST],
)]
#[IsGranted('ROLE_STUDENT')]
public function create{Resource}(
    Request $request,
    {Resource}Service ${resource}Service,
    SerializerService $serializerService,
    ValidatorInterface $validator,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    $data = json_decode($request->getContent(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_INVALID_JSON,
            'Invalid JSON payload',
            Response::HTTP_BAD_REQUEST
        );
    }

    try {
        Tools::checkExpectedKeys(['field_one', 'field_two'], $data);
    } catch (\Exception $exception) {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_INVALID_REQUEST,
            $exception->getMessage(),
            Response::HTTP_BAD_REQUEST
        );
    }

    ${resource} = ${resource}Service->create($data['field_one'], $user);

    $violations = $validator->validate(${resource});
    foreach ($violations as $violation) {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_VALIDATION,
            $violation->getMessage(),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    $dataJSON = $serializerService->serialize(${resource});
    $response = new JsonResponse();
    $response->setData(['{resource}' => json_decode($dataJSON)]);
    $response->setStatusCode(Response::HTTP_CREATED);
    return $response;
}
```

## GET single resource

```php
#[Route(
    '/api/{version}/{resource}s/{id}',
    name: 'app_{resource}_api_get_one',
    requirements: ['_format' => 'json', 'id' => Requirement::ULID],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_GET],
)]
#[IsGranted('ROLE_STUDENT')]
public function get{Resource}(
    Request $request,
    {Resource}Service ${resource}Service,
    SerializerService $serializerService,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    $publicId = $request->attributes->getString('id');
    ${resource} = ${resource}Service->findByPublicIdAndUser($publicId, $user);

    if (!${resource} instanceof {Resource}) {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_NOT_FOUND,
            '{Resource} not found',
            Response::HTTP_NOT_FOUND
        );
    }

    $dataJSON = $serializerService->serialize(${resource});
    $response = new JsonResponse();
    $response->setData(['{resource}' => json_decode($dataJSON)]);
    return $response;
}
```

## DELETE — 204 No Content

```php
#[Route(
    '/api/{version}/{resource}s/{id}',
    name: 'app_{resource}_api_delete',
    requirements: ['_format' => 'json', 'id' => Requirement::ULID],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_DELETE],
)]
#[IsGranted('ROLE_STUDENT')]
public function delete{Resource}(
    Request $request,
    {Resource}Service ${resource}Service,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    $publicId = $request->attributes->getString('id');
    ${resource} = ${resource}Service->findByPublicIdAndUser($publicId, $user);

    if (!${resource} instanceof {Resource}) {
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_NOT_FOUND,
            '{Resource} not found',
            Response::HTTP_NOT_FOUND
        );
    }

    ${resource}Service->delete(${resource});

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
```

## HTTP status semantics

Set the status via `Response::HTTP_*` constants — never bare integers:

| Action | Status |
|---|---|
| Read / update returning a body | `HTTP_OK` (200) |
| Resource created | `HTTP_CREATED` (201) |
| Delete / action with no body | `HTTP_NO_CONTENT` (204) |
| Validation failure | `HTTP_UNPROCESSABLE_ENTITY` (422) |

## Required imports

In addition to the existing import list, these actions need:

```php
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
```

Replace all `{resource}` / `{Resource}` placeholders with the lowercase/PascalCase form of `$ARGUMENTS`.
Adjust field names, service method names, and response keys to match the actual feature requirements.
