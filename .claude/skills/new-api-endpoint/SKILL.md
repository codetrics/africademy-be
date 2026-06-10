---
name: new-api-endpoint
description: Add a new JSON API endpoint to an existing OpenApiController, or scaffold a new one. Use when the user asks to add an API route, REST endpoint, or client-facing API action. All API endpoints in this project live under /api/v3/, use JWT auth via ClientAuth, and return JsonResponse.
disable-model-invocation: false
argument-hint: [ResourceName]
allowed-tools: Read Write Edit Glob Grep Bash(ls *)
---

Scaffold a new API endpoint for the `$ARGUMENTS` resource.

## Live context

Existing API controllers:
```!
ls src/Controller/ | grep OpenApi
```

Check if a controller already exists for this resource:
```!
ls src/Controller/$ARGUMENTSOpenApiController.php 2>/dev/null && echo "Controller exists — add endpoint to it" || echo "New controller needed"
```

Existing services (to find or plan the backing service):
```!
ls src/Service/*.php 2>/dev/null
```

## Instructions

1. **Check if an `$ARGUMENTSOpenApiController` already exists** — if so, add the new action to it rather than creating a new file.
2. **Read the existing controller first** (if it exists) to match its exact import list and code style.
3. **Read `src/Controller/BillingOpenApiController.php`** as the canonical reference for the pattern.
4. Determine which HTTP method(s) the user wants — default to GET if unspecified.
5. **No constructor** — all dependencies are injected as action-method parameters (autowired). Never add a constructor to an `OpenApiController`.

## Mandatory action order (every endpoint, no exceptions)

1. Auth check
2. Request parsing
3. Key/input validation
4. Business logic (service calls)
5. Response serialization

## Auth check — always first

```php
$user = $this->getUser();
if (!$user instanceof ClientAuth) {
    $exception = $this->createAccessDeniedException();
    return new JsonExceptionResponse(
        JsonExceptionResponse::ERROR_UNAUTHORIZED,
        $exception->getMessage(),
        Response::HTTP_UNAUTHORIZED
    );
}
$contact = $user->getContact();
```

## Request parsing rules

- JSON body: `json_decode($request->getContent(), true)` + `json_last_error() !== JSON_ERROR_NONE` guard
- Query params: `$request->query->getInt('key', default)` / `$request->query->getString('key')`
- Route params: `$request->attributes->getInt('id')`
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
    '/api/v3/{resource}',
    name: 'app_{resource}_open_api_get',
    requirements: ['_format' => 'json'],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_GET],
)]
public function get{Resource}s(
    Request $request,
    {Resource}Service ${resource}Service,
    PaginatorInterface $paginator,
    SerializerService $serializerService,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof ClientAuth) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }
    $contact = $user->getContact();

    $page = $request->query->getInt('page', 1);
    $limit = $request->query->getInt('limit', 10);

    $query = ${resource}Service->findByContactQuery($contact);
    $paginated = $paginator->paginate($query, $page, $limit);

    $dataJSON = $serializerService->serialize($paginated->getItems());
    $paginationJSON = $serializerService->serialize(new PaginationReturnType($paginated));

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
    '/api/v3/{resource}',
    name: 'app_{resource}_open_api_post',
    requirements: ['_format' => 'json'],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_POST],
)]
public function create{Resource}(
    Request $request,
    {Resource}Service ${resource}Service,
    SerializerService $serializerService,
    ValidatorInterface $validator,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof ClientAuth) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }
    $contact = $user->getContact();

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

    ${resource} = ${resource}Service->create($data['field_one'], $contact);

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
    '/api/v3/{resource}s/{id}',
    name: 'app_{resource}_open_api_get_one',
    requirements: ['_format' => 'json', 'id' => '\d+'],
    defaults: ['_format' => 'json'],
    methods: [Request::METHOD_GET],
)]
public function get{Resource}(
    Request $request,
    {Resource}Service ${resource}Service,
    SerializerService $serializerService,
): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof ClientAuth) {
        $exception = $this->createAccessDeniedException();
        return new JsonExceptionResponse(
            JsonExceptionResponse::ERROR_UNAUTHORIZED,
            $exception->getMessage(),
            Response::HTTP_UNAUTHORIZED
        );
    }
    $contact = $user->getContact();

    $id = $request->attributes->getInt('id');
    ${resource} = ${resource}Service->findByIdAndContact($id, $contact);

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

Replace all `{resource}` / `{Resource}` placeholders with the lowercase/PascalCase form of `$ARGUMENTS`.
Adjust field names, service method names, and response keys to match the actual feature requirements.
