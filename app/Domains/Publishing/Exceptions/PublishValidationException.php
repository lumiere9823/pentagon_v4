<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;

class PublishValidationException extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message,
        protected array $errors,
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
