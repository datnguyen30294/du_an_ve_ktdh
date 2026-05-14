<?php

namespace App\Common\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BusinessException extends Exception
{
    /**
     * @param  array<string, mixed>  $context  Additional context data for debugging
     */
    public function __construct(
        string $message,
        protected string $errorCode = 'BUSINESS_ERROR',
        int $httpStatusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        protected array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array{error_code: string, message: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
