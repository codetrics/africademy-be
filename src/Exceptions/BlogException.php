<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class BlogException extends Exception
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

    public static function postNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Blog post not found.');
    }

    public static function categoryNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Blog category not found.');
    }

    public static function categoryInUse(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'This category still has blog posts and cannot be deleted.');
    }
}
