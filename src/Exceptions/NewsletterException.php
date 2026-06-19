<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class NewsletterException extends Exception
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

    public static function alreadySubscribed(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'This email is already subscribed.');
    }

    public static function subscriptionNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Subscription not found.');
    }
}
