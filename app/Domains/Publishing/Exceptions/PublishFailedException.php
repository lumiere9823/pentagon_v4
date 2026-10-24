<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;
use Throwable;

class PublishFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Page publishing pipeline failed.',
        int $code = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
