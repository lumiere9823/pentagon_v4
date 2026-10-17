<?php

namespace App\Domains\DocumentEngine\Validation;

final class ValidationError
{
    public function __construct(
        private readonly string $path,
        private readonly string $code,
        private readonly string $message,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array{path: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
