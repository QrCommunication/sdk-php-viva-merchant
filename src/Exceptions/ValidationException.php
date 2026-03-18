<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Exceptions;

class ValidationException extends VivaException
{
    /** @var array<string, string[]> */
    public readonly array $errors;

    /**
     * @param  array<string, string[]>  $errors
     */
    public function __construct(array $errors, int $httpStatus = 422, ?array $responseBody = null)
    {
        $this->errors = $errors;
        $message = 'Validation failed: '.implode(', ', array_keys($errors));
        parent::__construct($message, $httpStatus, $responseBody);
    }
}
