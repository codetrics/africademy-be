<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domain exception for order/purchase business rules. Carries the API error type
 * and HTTP status so controllers can map it without branching.
 */
class OrderException extends Exception
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

    public static function courseNotPurchasable(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'This course is not available for purchase.');
    }

    public static function alreadyOwned(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'You already have access to this course.');
    }

    public static function orderNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Order not found.');
    }

    public static function notRefundable(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'This order is not eligible for a refund.');
    }

    public static function refundAlreadyRequested(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'A refund has already been requested for this order.');
    }
}
