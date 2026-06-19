<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class CommunityException extends Exception
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
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Post not found.');
    }

    public static function commentNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Comment not found.');
    }

    public static function invalidTag(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid post tag.');
    }

    public static function validation(string $message): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, $message);
    }
}
