<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domain exception for enrollment/progress business rules. Carries the API
 * error type and HTTP status so controllers can map it without branching.
 */
class EnrollmentException extends Exception
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

    public static function courseNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Course not found.');
    }

    public static function courseNotPublished(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'This course is not open for enrollment.');
    }

    public static function alreadyEnrolled(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'You are already enrolled in this course.');
    }

    public static function enrollmentNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Enrollment not found.');
    }

    public static function paymentRequired(): self
    {
        return new self(JsonExceptionResponse::ERROR_PAYMENT_REQUIRED, Response::HTTP_FORBIDDEN, 'This enrollment has not been paid for.');
    }

    public static function lessonNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Lesson not found.');
    }

    public static function lessonNotPublished(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'This lesson is not available.');
    }
}
