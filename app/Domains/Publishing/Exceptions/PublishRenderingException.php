<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;
use Throwable;

class PublishRenderingException extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message = 'Failed to render document into static HTML.',
        protected array $errors = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
