<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;
use Throwable;

class ReleaseStagingException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        string $message = 'Failed to stage or seal release artifact.',
        protected array $errors = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
