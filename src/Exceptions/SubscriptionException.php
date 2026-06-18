<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionException extends Exception
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

    public static function planNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Subscription plan not found.');
    }

    public static function paymentMethodNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, 'Payment method not found.');
    }

    public static function alreadySubscribed(): self
    {
        return new self(JsonExceptionResponse::ERROR_CONFLICT, Response::HTTP_CONFLICT, 'You already have an active subscription.');
    }

    public static function subscriptionNotFound(): self
    {
        return new self(JsonExceptionResponse::ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND, 'Subscription not found.');
    }

    public static function chargeFailed(): self
    {
        return new self(JsonExceptionResponse::ERROR_PAYMENT_REQUIRED, Response::HTTP_PAYMENT_REQUIRED, 'The payment could not be processed.');
    }
}
