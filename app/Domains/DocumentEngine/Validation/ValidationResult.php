<?php

namespace App\Domains\DocumentEngine\Validation;

final class ValidationResult
{
    /**
     * @param  list<ValidationError>  $errors
     * @param  array<string, mixed>|null  $sanitizedDocument
     */
    public function __construct(
        private readonly bool $valid,
        private readonly array $errors = [],
        private readonly ?array $sanitizedDocument = null,
    ) {}

    /**
     * @param  array<string, mixed>  $sanitizedDocument
     */
    public static function success(array $sanitizedDocument): self
    {
        return new self(valid: true, errors: [], sanitizedDocument: $sanitizedDocument);
    }

    /**
     * @param  list<ValidationError>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(valid: false, errors: $errors, sanitizedDocument: null);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array{path: string, code: string, message: string}>
     */
    public function getErrorsArray(): array
    {
        return array_map(fn (ValidationError $e) => $e->toArray(), $this->errors);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSanitizedDocument(): ?array
    {
        return $this->sanitizedDocument;
    }

    public function hasError(string $path): bool
    {
        foreach ($this->errors as $error) {
            if ($error->getPath() === $path) {
                return true;
            }
        }

        return false;
    }

    public function firstError(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }
}
