<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Enquiry;
use App\Exceptions\JsonExceptionResponse;
use App\Service\EnquiryService;
use App\Service\Helper\Tools;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EnquiryApiController extends AbstractController
{
    #[Route(
        '/api/{version}/public/enquiries',
        name: 'api_enquiry_submit',
        requirements: ['_format' => 'json', 'version' => 'v1'],
        defaults: ['_format' => 'json'],
        methods: [Request::METHOD_POST],
    )]
    public function submit(
        Request $request,
        EnquiryService $enquiryService,
        ValidatorInterface $validator,
        RateLimiterFactoryInterface $enquiryLimiter,
    ): JsonResponse {
        if (!$enquiryLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonExceptionResponse(
                JsonExceptionResponse::ERROR_RATE_LIMIT_EXCEEDED,
                'Too many attempts. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_JSON, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            Tools::checkExpectedKeys(['full_name', 'email', 'subject', 'message'], $data);
        } catch (Exception $exception) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_INVALID_REQUEST, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $enquiry = new Enquiry();
        $enquiry->setFullName(trim((string) $data['full_name']));
        $enquiry->setEmail(strtolower(trim((string) $data['email'])));
        $enquiry->setSubject(trim((string) $data['subject']));
        $enquiry->setMessage(trim((string) $data['message']));

        $violations = $validator->validate($enquiry);
        foreach ($violations as $violation) {
            return new JsonExceptionResponse(JsonExceptionResponse::ERROR_VALIDATION, $violation->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $enquiryService->submit($enquiry);

        return new JsonResponse(['message' => 'Thanks for getting in touch — we will be in contact soon.'], Response::HTTP_CREATED);
    }
}
