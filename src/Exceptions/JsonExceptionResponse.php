<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonExceptionResponse extends JsonResponse
{
    public const string ERROR_VALIDATION = 'validation_error';
    public const string ERROR_INVALID_JSON = 'invalid_json';
    public const string ERROR_INVALID_REQUEST = 'invalid_request';
    public const string ERROR_NOT_FOUND = 'not_found';
    public const string ERROR_CONFLICT = 'conflict';
    public const string ERROR_PAYMENT_REQUIRED = 'payment_required';
    public const string ERROR_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const string ERROR_UNAUTHORIZED = 'unauthorized';
    public const string ERROR_EMAIL_NOT_VERIFIED = 'email_not_verified';
    public const string ERROR_INTERNAL_SERVER_ERROR = 'internal_server_error';

    public function __construct(string $error, string $errorDescription, int $statusCode = self::HTTP_BAD_REQUEST)
    {
        parent::__construct(
            [
                'error' => $error,
                'error_description' => $errorDescription,
            ],
            $statusCode,
        );
    }
}
