<?php

namespace App\Domains\DocumentEngine\Validation;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Registry\ComponentRegistry;
use App\Domains\DocumentEngine\Sanitization\HtmlSanitizer;
use App\Domains\DocumentEngine\Schema\ComponentDefinition;
use JsonException;

class DocumentValidator
{
    private ComponentRegistry $registry;

    private HtmlSanitizer $sanitizer;

    public function __construct(
        ?ComponentRegistry $registry = null,
        ?HtmlSanitizer $sanitizer = null,
    ) {
        $this->registry = $registry ?? new ComponentRegistry;
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer;
    }

    /**
     * Validate and sanitize a page document structure.
     *
     * @param  string|array<string, mixed>  $payload  Raw JSON string or decoded array
     */
    public function validate(string|array $payload): ValidationResult
    {
        // 1. Check raw payload byte size if string
        if (is_string($payload)) {
            $byteLength = strlen($payload);
            if ($byteLength > DocumentLimits::MAX_PAYLOAD_BYTES) {
                return ValidationResult::failure([
                    new ValidationError(
                        path: 'document',
                        code: 'max_payload_size_exceeded',
                        message: "Payload size of {$byteLength} bytes exceeds the maximum limit of ".DocumentLimits::MAX_PAYLOAD_BYTES.' bytes.'
                    ),
                ]);
            }

            try {
                /** @var mixed $decoded */
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return ValidationResult::failure([
                    new ValidationError(
                        path: 'document',
                        code: 'invalid_json',
                        message: 'The document payload is not valid JSON.'
                    ),
                ]);
            }
        } else {
            $decoded = $payload;

            // Approximate payload size for array
            $encodedSize = strlen(json_encode($decoded) ?: '');
            if ($encodedSize > DocumentLimits::MAX_PAYLOAD_BYTES) {
                return ValidationResult::failure([
                    new ValidationError(
                        path: 'document',
                        code: 'max_payload_size_exceeded',
                        message: "Payload size of {$encodedSize} bytes exceeds the maximum limit of ".DocumentLimits::MAX_PAYLOAD_BYTES.' bytes.'
                    ),
                ]);
            }
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return ValidationResult::failure([
                new ValidationError(
                    path: 'document',
                    code: 'invalid_root_structure',
                    message: 'The document must be an associative JSON object.'
                ),
            ]);
        }

        /** @var list<ValidationError> $errors */
        $errors = [];

        // 2. Validate schemaVersion
        if (! isset($decoded['schemaVersion'])) {
            $errors[] = new ValidationError(
                path: 'schemaVersion',
                code: 'missing_schema_version',
                message: 'Field schemaVersion is required.'
            );
        } elseif (! is_int($decoded['schemaVersion'])) {
            $errors[] = new ValidationError(
                path: 'schemaVersion',
                code: 'invalid_schema_version_type',
                message: 'Field schemaVersion must be an integer.'
            );
        } elseif (! in_array($decoded['schemaVersion'], DocumentLimits::SUPPORTED_SCHEMA_VERSIONS, true)) {
            $supported = implode(', ', DocumentLimits::SUPPORTED_SCHEMA_VERSIONS);
            $errors[] = new ValidationError(
                path: 'schemaVersion',
                code: 'unsupported_schema_version',
                message: "Schema version '{$decoded['schemaVersion']}' is not supported. Supported versions: [{$supported}]."
            );
        }

        // 3. Validate root node existence
        if (! isset($decoded['root']) || ! is_array($decoded['root']) || array_is_list($decoded['root'])) {
            $errors[] = new ValidationError(
                path: 'root',
                code: 'missing_or_invalid_root',
                message: 'The document must contain a valid root node object.'
            );

            return ValidationResult::failure($errors);
        }

        /** @var array<string, mixed> $rootNode */
        $rootNode = $decoded['root'];

        // 4. Validate root node type
        $rootType = $rootNode['type'] ?? null;
        if (! is_string($rootType) || trim($rootType) === '') {
            $errors[] = new ValidationError(
                path: 'root.type',
                code: 'missing_root_type',
                message: 'Root node must have a type string.'
            );
        } else {
            $normalizedRootType = $this->registry->normalizeType($rootType);
            $rootDefinition = $this->registry->get($normalizedRootType);
            if (! $rootDefinition) {
                $errors[] = new ValidationError(
                    path: 'root.type',
                    code: 'unknown_component_type',
                    message: "Root component type '{$rootType}' is not in the allowlist."
                );
            } elseif (! $rootDefinition->canBeRoot) {
                $errors[] = new ValidationError(
                    path: 'root.type',
                    code: 'invalid_root_component',
                    message: "Component '{$rootType}' is not allowed as the document root."
                );
            }
        }

        // 5. Recursive tree validation & sanitization
        $seenNodeIds = [];
        $totalNodeCount = 0;

        $sanitizedRoot = $this->validateNode(
            node: $rootNode,
            path: 'root',
            currentDepth: 1,
            seenNodeIds: $seenNodeIds,
            totalNodeCount: $totalNodeCount,
            errors: $errors
        );

        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        $sanitizedDocument = [
            'schemaVersion' => $decoded['schemaVersion'],
            'root' => $sanitizedRoot,
        ];

        // Optional page settings if provided
        if (isset($decoded['pageSettings']) && is_array($decoded['pageSettings'])) {
            $sanitizedDocument['pageSettings'] = [
                'title' => is_string($decoded['pageSettings']['title'] ?? null)
                    ? mb_substr($decoded['pageSettings']['title'], 0, DocumentLimits::MAX_SHORT_STRING_LENGTH)
                    : null,
                'description' => is_string($decoded['pageSettings']['description'] ?? null)
                    ? mb_substr($decoded['pageSettings']['description'], 0, DocumentLimits::MAX_SHORT_STRING_LENGTH)
                    : null,
            ];
        }

        return ValidationResult::success($sanitizedDocument);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, bool>  $seenNodeIds
     * @param  list<ValidationError>  $errors
     * @return array<string, mixed> Sanitized node
     */
    private function validateNode(
        array $node,
        string $path,
        int $currentDepth,
        array &$seenNodeIds,
        int &$totalNodeCount,
        array &$errors,
    ): array {
        $totalNodeCount++;

        // Enforce total node limit
        if ($totalNodeCount > DocumentLimits::MAX_TOTAL_NODES) {
            $errors[] = new ValidationError(
                path: $path,
                code: 'max_nodes_exceeded',
                message: 'Total document node count exceeds the limit of '.DocumentLimits::MAX_TOTAL_NODES.'.'
            );

            return [];
        }

        // Enforce tree depth limit
        if ($currentDepth > DocumentLimits::MAX_TREE_DEPTH) {
            $errors[] = new ValidationError(
                path: $path,
                code: 'max_depth_exceeded',
                message: 'Tree depth exceeds maximum allowed depth of '.DocumentLimits::MAX_TREE_DEPTH.'.'
            );

            return [];
        }

        // Validate node ID
        $nodeId = $node['id'] ?? null;
        if (! is_string($nodeId) || trim($nodeId) === '') {
            $errors[] = new ValidationError(
                path: "{$path}.id",
                code: 'missing_node_id',
                message: "Node at '{$path}' must have a string id."
            );
        } else {
            if (! preg_match(DocumentLimits::NODE_ID_REGEX, $nodeId)) {
                $errors[] = new ValidationError(
                    path: "{$path}.id",
                    code: 'invalid_node_id_format',
                    message: "Node id '{$nodeId}' at '{$path}' must be 3-64 alphanumeric characters, underscores, or hyphens."
                );
            } elseif (isset($seenNodeIds[$nodeId])) {
                $errors[] = new ValidationError(
                    path: "{$path}.id",
                    code: 'duplicate_node_id',
                    message: "Duplicate node id '{$nodeId}' found at '{$path}'."
                );
            } else {
                $seenNodeIds[$nodeId] = true;
            }
        }

        // Validate node type
        $rawType = $node['type'] ?? null;
        if (! is_string($rawType) || trim($rawType) === '') {
            $errors[] = new ValidationError(
                path: "{$path}.type",
                code: 'missing_node_type',
                message: "Node at '{$path}' must have a type string."
            );

            return [];
        }

        $normalizedType = $this->registry->normalizeType($rawType);
        $definition = $this->registry->get($normalizedType);

        if (! $definition) {
            $errors[] = new ValidationError(
                path: "{$path}.type",
                code: 'unknown_component_type',
                message: "Component type '{$rawType}' is not in the allowlist."
            );

            return [];
        }

        // Validate props
        $rawProps = $node['props'] ?? [];
        if (! is_array($rawProps)) {
            $errors[] = new ValidationError(
                path: "{$path}.props",
                code: 'invalid_props_type',
                message: "Props for node at '{$path}' must be an object."
            );
            $sanitizedProps = [];
        } else {
            $sanitizedProps = $definition->validateProps(
                rawProps: $rawProps,
                nodePath: $path,
                errors: $errors,
                sanitizer: $this->sanitizer
            );
        }

        // Validate children
        $children = $node['children'] ?? [];
        $sanitizedChildren = [];

        if (! is_array($children)) {
            $errors[] = new ValidationError(
                path: "{$path}.children",
                code: 'invalid_children_type',
                message: "Children for node at '{$path}' must be an array."
            );
        } else {
            if (! $definition->allowChildren && ! empty($children)) {
                $errors[] = new ValidationError(
                    path: "{$path}.children",
                    code: 'children_not_allowed',
                    message: "Component '{$normalizedType}' is a leaf component and cannot have children."
                );
            } else {
                $childCount = count($children);
                if ($childCount > DocumentLimits::MAX_CHILDREN_PER_NODE) {
                    $errors[] = new ValidationError(
                        path: "{$path}.children",
                        code: 'max_children_exceeded',
                        message: "Node at '{$path}' has {$childCount} children, exceeding maximum limit of ".DocumentLimits::MAX_CHILDREN_PER_NODE.'.'
                    );
                } else {
                    foreach ($children as $idx => $childNode) {
                        $childPath = "{$path}.children[{$idx}]";
                        if (! is_array($childNode)) {
                            $errors[] = new ValidationError(
                                path: $childPath,
                                code: 'invalid_child_node',
                                message: "Child node at '{$childPath}' must be an object."
                            );

                            continue;
                        }

                        $sanitizedChildren[] = $this->validateNode(
                            node: $childNode,
                            path: $childPath,
                            currentDepth: $currentDepth + 1,
                            seenNodeIds: $seenNodeIds,
                            totalNodeCount: $totalNodeCount,
                            errors: $errors
                        );
                    }
                }
            }
        }

        $sanitizedStyles = $this->validateStyles(
            rawStyles: $node['styles'] ?? null,
            componentType: $normalizedType,
            definition: $definition,
            nodePath: $path,
            errors: $errors
        );

        $nodeResult = [
            'id' => $nodeId,
            'type' => $normalizedType,
            'props' => $sanitizedProps,
            'children' => $sanitizedChildren,
        ];

        if ($sanitizedStyles !== null && ! empty($sanitizedStyles)) {
            $nodeResult['styles'] = $sanitizedStyles;
        }

        return $nodeResult;
    }

    /**
     * @param  list<ValidationError>  $errors
     * @return array<string, array<string, mixed>>|null
     */
    private function validateStyles(
        mixed $rawStyles,
        string $componentType,
        ComponentDefinition $definition,
        string $nodePath,
        array &$errors,
    ): ?array {
        if ($rawStyles === null) {
            return null;
        }

        if (! is_array($rawStyles)) {
            $errors[] = new ValidationError(
                path: "{$nodePath}.styles",
                code: 'invalid_styles_format',
                message: "Field 'styles' must be an associative object."
            );

            return null;
        }

        // Empty array from {} or [] prunes cleanly
        if (empty($rawStyles)) {
            return null;
        }

        if (array_is_list($rawStyles)) {
            $errors[] = new ValidationError(
                path: "{$nodePath}.styles",
                code: 'invalid_styles_format',
                message: "Field 'styles' must be an associative object with viewport keys."
            );

            return null;
        }

        $allowedViewports = ['tablet', 'mobile'];
        $sanitizedStyles = [];

        foreach ($rawStyles as $viewport => $viewportStyles) {
            $viewportPath = "{$nodePath}.styles.{$viewport}";

            if (! is_string($viewport) || ! in_array($viewport, $allowedViewports, true)) {
                $errors[] = new ValidationError(
                    path: $viewportPath,
                    code: 'invalid_viewport_key',
                    message: "Viewport '{$viewport}' is not allowed in styles. Only ['tablet', 'mobile'] are supported."
                );

                continue;
            }

            if (! is_array($viewportStyles)) {
                $errors[] = new ValidationError(
                    path: $viewportPath,
                    code: 'invalid_styles_format',
                    message: "Styles for viewport '{$viewport}' must be an associative object."
                );

                continue;
            }

            if (empty($viewportStyles)) {
                // Empty viewport dictionary prunes cleanly
                continue;
            }

            if (array_is_list($viewportStyles)) {
                $errors[] = new ValidationError(
                    path: $viewportPath,
                    code: 'invalid_styles_format',
                    message: "Styles for viewport '{$viewport}' must be an associative object."
                );

                continue;
            }

            $sanitizedViewport = [];

            foreach ($viewportStyles as $propKey => $propVal) {
                $propPath = "{$viewportPath}.{$propKey}";

                if (! is_string($propKey)) {
                    continue;
                }

                if (! $this->registry->isResponsiveOverrideAllowed($componentType, $propKey)) {
                    $errors[] = new ValidationError(
                        path: $propPath,
                        code: 'disallowed_style_override',
                        message: "Property '{$propKey}' cannot be overridden responsively for component '{$componentType}'."
                    );

                    continue;
                }

                if ($propKey === 'display') {
                    if (! is_string($propVal) || ! in_array($propVal, ['visible', 'hidden'], true)) {
                        $errors[] = new ValidationError(
                            path: $propPath,
                            code: 'invalid_enum_value',
                            message: "Property 'display' must be one of ['visible', 'hidden']."
                        );

                        continue;
                    }
                    $sanitizedViewport['display'] = $propVal;

                    continue;
                }

                $validatedVal = $definition->validateSingleProp(
                    propName: $propKey,
                    val: $propVal,
                    propPath: $propPath,
                    errors: $errors,
                    sanitizer: $this->sanitizer
                );

                if ($validatedVal !== null || (isset($definition->propSchemas[$propKey]) && $propVal === null)) {
                    $sanitizedViewport[$propKey] = $validatedVal;
                }
            }

            if (! empty($sanitizedViewport)) {
                $sanitizedStyles[$viewport] = $sanitizedViewport;
            }
        }

        return ! empty($sanitizedStyles) ? $sanitizedStyles : null;
    }

    /**
     * Create a canonical empty Document Schema v1 document structure.
     *
     * @return array<string, mixed>
     */
    public static function createEmptyDocument(string $title = 'New Page'): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_section_default',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'node_container_default',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => $title,
                'description' => '',
            ],
        ];
    }
}
