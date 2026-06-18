<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class BundleException extends Exception
{
    private function __construct(
        private readonly string $errorType,
        private readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function courseNotEligible(): self
    {
        return new self(
            JsonExceptionResponse::ERROR_VALIDATION,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'A bundle may only contain your own published courses.',
        );
    }

    public static function notFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Bundle not found.');
    }

    public static function notPurchasable(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'This bundle is not available for purchase.');
    }

    public static function alreadyOwned(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'You already have access to all courses in this bundle.');
    }
}
