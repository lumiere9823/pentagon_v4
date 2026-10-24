<?php

namespace App\Domains\Publishing\Exceptions;

use RuntimeException;
use Throwable;

class MediaUnavailableException extends RuntimeException
{
    /**
     * @param  list<int>  $unavailableIds
     */
    public function __construct(
        string $message = 'One or more referenced media assets are unavailable or deleted.',
        protected array $unavailableIds = [],
        int $code = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return list<int>
     */
    public function getUnavailableIds(): array
    {
        return $this->unavailableIds;
    }
}
