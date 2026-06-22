<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Helper\Tools;
use App\Exceptions\CertificateException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\CertificateService;
use App\Service\ReturnType\PaginationReturnType;
use App\Service\SerializerService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

final class CertificateApiController extends AbstractController
{
    #[Route(
        '/api/{version}/students/certificates',
        name: 'api_certificate_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function list(
        Request $request,
        CertificateService $certificateService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $queryBuilder = $certificateService->createStudentCertificatesQueryBuilder($user);
        $pagination = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), Tools::clampLimit($request->query->getInt('limit', 10)));

        $response = new JsonResponse();
        $response->setData([
            'certificates' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/students/certificates/{id}/download',
        name: 'api_certificate_download',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_STUDENT')]
    public function download(
        Request $request,
        CertificateService $certificateService,
    ): Response {
        $user = $this->narrowUser();
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        try {
            $certificate = $certificateService->getStudentCertificate($user, Ulid::fromString($request->attributes->getString('id')));
        } catch (CertificateException $exception) {
            return $this->mapException($exception);
        }

        return $certificateService->downloadResponse($certificate);
    }

    #[Route(
        '/api/{version}/certificates/verify/{credentialId}',
        name: 'api_certificate_verify',
        requirements: ['_format' => 'json', 'version' => 'v1', 'credentialId' => '[a-f0-9]{32}'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    public function verify(
        Request $request,
        CertificateService $certificateService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $certificate = $certificateService->verify($request->attributes->getString('credentialId'));
        } catch (CertificateException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData([
            'valid' => true,
            'certificate' => json_decode($serializerService->serialize($certificate)),
        ]);

        return $response;
    }

    private function mapException(CertificateException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
    }

    private function narrowUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function unauthorized(): JsonExceptionResponse
    {
        $exception = $this->createAccessDeniedException();

        return new JsonExceptionResponse(JsonExceptionResponse::ERROR_UNAUTHORIZED, $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
