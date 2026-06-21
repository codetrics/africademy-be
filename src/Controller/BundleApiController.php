<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bundle;
use App\Entity\Money;
use App\Entity\User;
use App\Exceptions\BundleException;
use App\Exceptions\JsonExceptionResponse;
use App\Repository\BundleRepository;
use App\Security\Voter\BundleVoter;
use App\Service\BundleService;
use App\Service\Helper\Tools;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BundleApiController extends AbstractController
{
    #[Route(
        '/api/{version}/bundles',
        name: 'api_bundle_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        BundleRepository $bundleRepository,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $owner = $this->isGranted(User::ROLE_FACILITATOR) ? $user : null;
        $pagination = $paginator->paginate(
            $bundleRepository->createCatalogQueryBuilder($owner),
            $request->query->getInt('page', 1),
            Tools::clampLimit($request->query->getInt('limit', 10)),
        );

        $response = new JsonResponse();
        $response->setData([
            'bundles' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles',
        name: 'api_bundle_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(User::ROLE_FACILITATOR)]
    public function create(
        Request $request,
        BundleService $bundleService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            Tools::checkExpectedKeys(['title'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $bundle = new Bundle();
        $bundle->setOwner($user);
        $bundle->setTitle((string) $data['title']);
        $this->applyWritableFields($bundle, $data);

        $violation = $this->firstViolation($bundle, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $violation, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bundleService->create($bundle);

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles/{id}',
        name: 'api_bundle_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function get(
        Request $request,
        BundleRepository $bundleRepository,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $bundle = $bundleRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$bundle instanceof Bundle || ($bundle->getStatus()->value !== 'published' && $bundle->getOwner()->getId() !== $user->getId())) {
            return $this->bundleNotFound();
        }

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle))]);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles/{id}',
        name: 'api_bundle_update',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PATCH],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function update(
        Request $request,
        BundleRepository $bundleRepository,
        BundleService $bundleService,
        SerializerService $serializerService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $bundle = $bundleRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$bundle instanceof Bundle) {
            return $this->bundleNotFound();
        }

        $this->denyAccessUnlessGranted(BundleVoter::EDIT, $bundle);

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (array_key_exists('title', $data)) {
            $bundle->setTitle((string) $data['title']);
        }
        $this->applyWritableFields($bundle, $data);

        $violation = $this->firstViolation($bundle, $validator);
        if (!is_null($violation)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $violation, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bundleService->update($bundle);

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle))]);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles/{id}/courses',
        name: 'api_bundle_set_courses',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_PUT],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function setCourses(
        Request $request,
        BundleRepository $bundleRepository,
        BundleService $bundleService,
        SerializerService $serializerService,
    ): JsonResponse {
        $bundle = $bundleRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$bundle instanceof Bundle) {
            return $this->bundleNotFound();
        }

        $this->denyAccessUnlessGranted(BundleVoter::EDIT, $bundle);

        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (!array_key_exists('course_ids', $data) || !is_array($data['course_ids'])) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'course_ids must be a list.', Response::HTTP_BAD_REQUEST);
        }

        $coursePublicIds = [];
        foreach ($data['course_ids'] as $courseId) {
            if (!Ulid::isValid((string) $courseId)) {
                return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, 'Invalid course id.', Response::HTTP_BAD_REQUEST);
            }
            $coursePublicIds[] = Ulid::fromString((string) $courseId);
        }

        try {
            $bundleService->setCourses($bundle, $coursePublicIds);
        } catch (BundleException $exception) {
            return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
        }

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle))]);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles/{id}/publish',
        name: 'api_bundle_publish',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function publish(
        Request $request,
        BundleRepository $bundleRepository,
        BundleService $bundleService,
        SerializerService $serializerService,
    ): JsonResponse {
        $bundle = $bundleRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$bundle instanceof Bundle) {
            return $this->bundleNotFound();
        }

        $this->denyAccessUnlessGranted(BundleVoter::PUBLISH, $bundle);
        $bundleService->publish($bundle);

        $response = new JsonResponse();
        $response->setData(['bundle' => json_decode($serializerService->serialize($bundle))]);

        return $response;
    }

    #[Route(
        '/api/{version}/bundles/{id}',
        name: 'api_bundle_delete',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function delete(
        Request $request,
        BundleRepository $bundleRepository,
        BundleService $bundleService,
    ): JsonResponse {
        $bundle = $bundleRepository->findOneByPublicId(Ulid::fromString($request->attributes->getString('id')));
        if (!$bundle instanceof Bundle) {
            return $this->bundleNotFound();
        }

        $this->denyAccessUnlessGranted(BundleVoter::DELETE, $bundle);
        $bundleService->delete($bundle);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyWritableFields(Bundle $bundle, array $data): void
    {
        if (array_key_exists('description', $data)) {
            $bundle->setDescription(is_null($data['description']) ? null : (string) $data['description']);
        }

        if (array_key_exists('thumbnail', $data)) {
            $bundle->setThumbnailPath(is_null($data['thumbnail']) ? null : (string) $data['thumbnail']);
        }

        if (array_key_exists('price_amount_cents', $data)) {
            $currency = array_key_exists('price_currency', $data) ? (string) $data['price_currency'] : Money::DEFAULT_CURRENCY;
            $bundle->setPrice(new Money(max(0, (int) $data['price_amount_cents']), $currency));
        }
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decode(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }

    private function firstViolation(Bundle $bundle, ValidatorInterface $validator): ?string
    {
        foreach ($validator->validate($bundle) as $violation) {
            return (string) $violation->getMessage();
        }

        return null;
    }

    private function narrowUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function bundleNotFound(): JsonExceptionResponse
    {
        return new JsonExceptionResponse(JsonExceptionResponse::ERROR_NOT_FOUND, 'Bundle not found', Response::HTTP_NOT_FOUND);
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(JsonExceptionResponse::ERROR_UNAUTHORIZED, $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
