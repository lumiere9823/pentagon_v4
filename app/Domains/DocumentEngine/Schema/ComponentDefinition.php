<?php

namespace App\Domains\DocumentEngine\Schema;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Sanitization\HtmlSanitizer;
use App\Domains\DocumentEngine\Validation\ValidationError;

class ComponentDefinition
{
    /**
     * @param  string  $type  Component type identifier
     * @param  string  $label  Human-readable name
     * @param  string  $category  layout | typography | media | interactive | content
     * @param  bool  $allowChildren  Whether the component can hold child nodes
     * @param  bool  $canBeRoot  Whether the component is valid as the root node
     * @param  array<string, array<string, mixed>>  $propSchemas  Definitions of supported props
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $category,
        public readonly bool $allowChildren,
        public readonly bool $canBeRoot,
        public readonly array $propSchemas = [],
    ) {}

    /**
     * Validate and sanitize props against the schema definition.
     *
     * @param  array<string, mixed>  $rawProps
     * @param  list<ValidationError>  $errors
     * @return array<string, mixed> Sanitized props
     */
    public function validateProps(
        array $rawProps,
        string $nodePath,
        array &$errors,
        HtmlSanitizer $sanitizer,
    ): array {
        $sanitized = [];

        // Check for disallowed/unknown props
        foreach (array_keys($rawProps) as $propName) {
            if (! isset($this->propSchemas[$propName])) {
                $errors[] = new ValidationError(
                    path: "{$nodePath}.props.{$propName}",
                    code: 'unknown_prop',
                    message: "Unknown property '{$propName}' for component '{$this->type}'."
                );
            }
        }

        // Validate known props
        foreach ($this->propSchemas as $propName => $schema) {
            $propPath = "{$nodePath}.props.{$propName}";
            $hasProp = array_key_exists($propName, $rawProps);
            $val = $hasProp ? $rawProps[$propName] : null;

            $isRequired = $schema['required'] ?? false;
            $type = $schema['type'] ?? 'string';
            $default = $schema['default'] ?? null;

            if (! $hasProp || $val === null) {
                if ($isRequired) {
                    $errors[] = new ValidationError(
                        path: $propPath,
                        code: 'required_prop_missing',
                        message: "Required property '{$propName}' is missing for component '{$this->type}'."
                    );
                } else {
                    $sanitized[$propName] = $default;
                }

                continue;
            }

            // Type and constraint validation
            switch ($type) {
                case 'string':
                    if (! is_string($val)) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_type',
                            message: "Property '{$propName}' must be a string."
                        );
                        break;
                    }

                    $max = $schema['maxLength'] ?? DocumentLimits::MAX_SHORT_STRING_LENGTH;
                    if (mb_strlen($val) > $max) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'max_length_exceeded',
                            message: "Property '{$propName}' exceeds maximum length of {$max} characters."
                        );
                        break;
                    }

                    if (isset($schema['enum']) && ! in_array($val, $schema['enum'], true)) {
                        $allowed = implode(', ', $schema['enum']);
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_enum_value',
                            message: "Property '{$propName}' has invalid value '{$val}'. Allowed: [{$allowed}]."
                        );
                        break;
                    }

                    if (! empty($schema['isUrl'])) {
                        if (! $sanitizer->isSafeUrl($val)) {
                            $errors[] = new ValidationError(
                                path: $propPath,
                                code: 'unsafe_url',
                                message: "Property '{$propName}' contains an unsafe or forbidden URL scheme."
                            );
                            break;
                        }
                    }

                    if (! empty($schema['isHtml'])) {
                        $sanitized[$propName] = $sanitizer->sanitize($val);
                    } else {
                        $sanitized[$propName] = $val;
                    }
                    break;

                case 'integer':
                    if (! is_int($val)) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_type',
                            message: "Property '{$propName}' must be an integer."
                        );
                        break;
                    }

                    if (isset($schema['min']) && $val < $schema['min']) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'min_value_exceeded',
                            message: "Property '{$propName}' must be at least {$schema['min']}."
                        );
                        break;
                    }

                    if (isset($schema['max']) && $val > $schema['max']) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'max_value_exceeded',
                            message: "Property '{$propName}' must be at most {$schema['max']}."
                        );
                        break;
                    }

                    if (isset($schema['enum']) && ! in_array($val, $schema['enum'], true)) {
                        $allowed = implode(', ', $schema['enum']);
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_enum_value',
                            message: "Property '{$propName}' has invalid value '{$val}'. Allowed: [{$allowed}]."
                        );
                        break;
                    }

                    $sanitized[$propName] = $val;
                    break;

                case 'boolean':
                    if (! is_bool($val)) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_type',
                            message: "Property '{$propName}' must be a boolean."
                        );
                        break;
                    }
                    $sanitized[$propName] = $val;
                    break;

                case 'array':
                    if (! is_array($val) || array_is_list($val) === false) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_type',
                            message: "Property '{$propName}' must be a sequential array list."
                        );
                        break;
                    }

                    $maxItems = $schema['maxItems'] ?? 50;
                    if (count($val) > $maxItems) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'max_items_exceeded',
                            message: "Property '{$propName}' cannot contain more than {$maxItems} items."
                        );
                        break;
                    }

                    // Validate item schema if specified (e.g. FAQ items)
                    if (isset($schema['itemSchema'])) {
                        $sanitizedItems = [];
                        foreach ($val as $idx => $item) {
                            $itemPath = "{$propPath}[{$idx}]";
                            if (! is_array($item)) {
                                $errors[] = new ValidationError(
                                    path: $itemPath,
                                    code: 'invalid_item_type',
                                    message: "Item at index {$idx} must be an object."
                                );

                                continue;
                            }

                            $sanitizedItem = [];
                            foreach ($schema['itemSchema'] as $field => $fieldRules) {
                                $fieldPath = "{$itemPath}.{$field}";
                                if (! isset($item[$field])) {
                                    if (! empty($fieldRules['required'])) {
                                        $errors[] = new ValidationError(
                                            path: $fieldPath,
                                            code: 'required_field_missing',
                                            message: "Field '{$field}' is required in {$propName} item."
                                        );
                                    } else {
                                        $sanitizedItem[$field] = $fieldRules['default'] ?? null;
                                    }

                                    continue;
                                }

                                if (! is_string($item[$field])) {
                                    $errors[] = new ValidationError(
                                        path: $fieldPath,
                                        code: 'invalid_type',
                                        message: "Field '{$field}' must be a string."
                                    );

                                    continue;
                                }

                                $fMax = $fieldRules['maxLength'] ?? DocumentLimits::MAX_SHORT_STRING_LENGTH;
                                if (mb_strlen($item[$field]) > $fMax) {
                                    $errors[] = new ValidationError(
                                        path: $fieldPath,
                                        code: 'max_length_exceeded',
                                        message: "Field '{$field}' exceeds maximum length of {$fMax}."
                                    );

                                    continue;
                                }

                                if (! empty($fieldRules['isHtml'])) {
                                    $sanitizedItem[$field] = $sanitizer->sanitize($item[$field]);
                                } else {
                                    $sanitizedItem[$field] = $item[$field];
                                }
                            }
                            $sanitizedItems[] = $sanitizedItem;
                        }
                        $sanitized[$propName] = $sanitizedItems;
                    } else {
                        $sanitized[$propName] = $val;
                    }
                    break;

                default:
                    $sanitized[$propName] = $val;
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed> Public metadata for editor client
     */
    public function toMetadata(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'category' => $this->category,
            'allowChildren' => $this->allowChildren,
            'canBeRoot' => $this->canBeRoot,
            'props' => $this->propSchemas,
        ];
    }
}
