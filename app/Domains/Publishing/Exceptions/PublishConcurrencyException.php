<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;
use Throwable;

class PublishConcurrencyException extends RuntimeException
{
    public function __construct(
        string $message = 'Publishing is already in progress for this page.',
        int $code = 409,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
