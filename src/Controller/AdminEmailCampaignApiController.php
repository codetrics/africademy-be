<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\CampaignSegment;
use App\Exceptions\EmailCampaignException;
use App\Exceptions\JsonExceptionResponse;
use App\Service\EmailCampaignService;
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

final class AdminEmailCampaignApiController extends AbstractController
{
    #[Route(
        '/api/{version}/admin/email-campaigns',
        name: 'api_admin_email_campaign_list',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        EmailCampaignService $emailCampaignService,
        PaginatorInterface $paginator,
        SerializerService $serializerService,
    ): JsonResponse {
        $pagination = $paginator->paginate(
            $emailCampaignService->listQueryBuilder(),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 10),
        );

        $response = new JsonResponse();
        $response->setData([
            'campaigns' => json_decode($serializerService->serialize($pagination->getItems())),
            'pagination' => json_decode($serializerService->serialize(new PaginationReturnType($pagination))),
        ]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/email-campaigns',
        name: 'api_admin_email_campaign_create',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EmailCampaignService $emailCampaignService,
        SerializerService $serializerService,
    ): JsonResponse {
        $data = $this->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            Tools::checkExpectedKeys(['subject', 'heading', 'body', 'segment'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $segment = CampaignSegment::tryFrom((string) $data['segment']);
        if (is_null($segment)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, 'Invalid campaign segment.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $creator = $this->getUser();
        $campaign = $emailCampaignService->createCampaign(
            $creator instanceof User ? $creator : null,
            (string) $data['subject'],
            (string) $data['heading'],
            (string) $data['body'],
            $segment,
        );

        $response = new JsonResponse();
        $response->setData(['campaign' => json_decode($serializerService->serialize($campaign))]);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/email-campaigns/{id}',
        name: 'api_admin_email_campaign_get',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function show(
        Request $request,
        EmailCampaignService $emailCampaignService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $campaign = $emailCampaignService->resolveCampaign(Ulid::fromString($request->attributes->getString('id')));
        } catch (EmailCampaignException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['campaign' => json_decode($serializerService->serialize($campaign))]);

        return $response;
    }

    #[Route(
        '/api/{version}/admin/email-campaigns/{id}/send',
        name: 'api_admin_email_campaign_send',
        requirements: ['_format' => 'json', 'version' => 'v1', 'id' => Requirement::ULID],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function send(
        Request $request,
        EmailCampaignService $emailCampaignService,
        SerializerService $serializerService,
    ): JsonResponse {
        try {
            $campaign = $emailCampaignService->resolveCampaign(Ulid::fromString($request->attributes->getString('id')));
            $emailCampaignService->send($campaign);
        } catch (EmailCampaignException $exception) {
            return $this->mapException($exception);
        }

        $response = new JsonResponse();
        $response->setData(['campaign' => json_decode($serializerService->serialize($campaign))]);

        return $response;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decode(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        return $data;
    }

    private function mapException(EmailCampaignException $exception): JsonExceptionResponse
    {
        return new JsonExceptionResponse($exception->getErrorType(), $exception->getMessage(), $exception->getStatusCode());
    }
}
