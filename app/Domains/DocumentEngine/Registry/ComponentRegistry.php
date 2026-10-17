<?php

namespace App\Domains\DocumentEngine\Registry;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Schema\ComponentDefinition;

class ComponentRegistry
{
    /**
     * @var array<string, ComponentDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, string> Alias mapping (e.g. "Rich Text" -> "RichText")
     */
    private array $aliases = [
        'Rich Text' => 'RichText',
        'Card Grid' => 'CardGrid',
    ];

    public function __construct()
    {
        $this->registerCoreComponents();
    }

    public function has(string $type): bool
    {
        $normalized = $this->normalizeType($type);

        return isset($this->definitions[$normalized]);
    }

    public function get(string $type): ?ComponentDefinition
    {
        $normalized = $this->normalizeType($type);

        return $this->definitions[$normalized] ?? null;
    }

    /**
     * @return list<string> Allowlist of core component types
     */
    public function getAllowlist(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * @return array<string, array<string, mixed>> Public registry metadata
     */
    public function getMetadata(): array
    {
        $meta = [];
        foreach ($this->definitions as $type => $def) {
            $meta[$type] = $def->toMetadata();
        }

        return $meta;
    }

    public function normalizeType(string $type): string
    {
        $trimmed = trim($type);

        return $this->aliases[$trimmed] ?? $trimmed;
    }

    private function register(ComponentDefinition $definition): void
    {
        $this->definitions[$definition->type] = $definition;
    }

    private function registerCoreComponents(): void
    {
        // 1. Section
        $this->register(new ComponentDefinition(
            type: 'Section',
            label: 'Section',
            category: 'layout',
            allowChildren: true,
            canBeRoot: true,
            propSchemas: [
                'padding' => [
                    'type' => 'string',
                    'enum' => ['none', 'small', 'medium', 'large'],
                    'default' => 'medium',
                ],
                'background' => [
                    'type' => 'string',
                    'enum' => ['transparent', 'muted', 'primary', 'dark'],
                    'default' => 'transparent',
                ],
            ]
        ));

        // 2. Container
        $this->register(new ComponentDefinition(
            type: 'Container',
            label: 'Container',
            category: 'layout',
            allowChildren: true,
            canBeRoot: true,
            propSchemas: [
                'maxWidth' => [
                    'type' => 'string',
                    'enum' => ['sm', 'md', 'lg', 'xl', 'full'],
                    'default' => 'lg',
                ],
                'centered' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ]
        ));

        // 3. Heading
        $this->register(new ComponentDefinition(
            type: 'Heading',
            label: 'Heading',
            category: 'typography',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'text' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => 255,
                ],
                'level' => [
                    'type' => 'integer',
                    'required' => true,
                    'enum' => [1, 2, 3, 4, 5, 6],
                    'default' => 2,
                ],
                'align' => [
                    'type' => 'string',
                    'enum' => ['left', 'center', 'right'],
                    'default' => 'left',
                ],
            ]
        ));

        // 4. RichText
        $this->register(new ComponentDefinition(
            type: 'RichText',
            label: 'Rich Text',
            category: 'typography',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'content' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => DocumentLimits::MAX_RICH_TEXT_LENGTH,
                    'isHtml' => true,
                ],
            ]
        ));

        // 5. Image
        $this->register(new ComponentDefinition(
            type: 'Image',
            label: 'Image',
            category: 'media',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'src' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => DocumentLimits::MAX_URL_LENGTH,
                    'isUrl' => true,
                ],
                'alt' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 255,
                ],
                'caption' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 255,
                ],
                'aspectRatio' => [
                    'type' => 'string',
                    'enum' => ['auto', '16:9', '4:3', '1:1'],
                    'default' => 'auto',
                ],
            ]
        ));

        // 6. Button
        $this->register(new ComponentDefinition(
            type: 'Button',
            label: 'Button',
            category: 'interactive',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'label' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => 100,
                ],
                'url' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => DocumentLimits::MAX_URL_LENGTH,
                    'isUrl' => true,
                ],
                'variant' => [
                    'type' => 'string',
                    'enum' => ['primary', 'secondary', 'outline', 'ghost'],
                    'default' => 'primary',
                ],
                'size' => [
                    'type' => 'string',
                    'enum' => ['sm', 'md', 'lg'],
                    'default' => 'md',
                ],
                'target' => [
                    'type' => 'string',
                    'enum' => ['_self', '_blank'],
                    'default' => '_self',
                ],
            ]
        ));

        // 7. Hero
        $this->register(new ComponentDefinition(
            type: 'Hero',
            label: 'Hero',
            category: 'content',
            allowChildren: true,
            canBeRoot: true,
            propSchemas: [
                'title' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => 255,
                ],
                'subtitle' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 500,
                ],
                'align' => [
                    'type' => 'string',
                    'enum' => ['left', 'center'],
                    'default' => 'center',
                ],
                'ctaText' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 100,
                ],
                'ctaUrl' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => DocumentLimits::MAX_URL_LENGTH,
                    'isUrl' => true,
                ],
            ]
        ));

        // 8. Columns
        $this->register(new ComponentDefinition(
            type: 'Columns',
            label: 'Columns',
            category: 'layout',
            allowChildren: true,
            canBeRoot: false,
            propSchemas: [
                'count' => [
                    'type' => 'integer',
                    'enum' => [2, 3, 4],
                    'default' => 2,
                ],
                'gap' => [
                    'type' => 'string',
                    'enum' => ['small', 'medium', 'large'],
                    'default' => 'medium',
                ],
            ]
        ));

        // 9. CardGrid
        $this->register(new ComponentDefinition(
            type: 'CardGrid',
            label: 'Card Grid',
            category: 'layout',
            allowChildren: true,
            canBeRoot: false,
            propSchemas: [
                'columns' => [
                    'type' => 'integer',
                    'enum' => [2, 3, 4],
                    'default' => 3,
                ],
                'gap' => [
                    'type' => 'string',
                    'enum' => ['small', 'medium', 'large'],
                    'default' => 'medium',
                ],
            ]
        ));

        // 10. FAQ
        $this->register(new ComponentDefinition(
            type: 'FAQ',
            label: 'FAQ',
            category: 'content',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'items' => [
                    'type' => 'array',
                    'maxItems' => 20,
                    'default' => [],
                    'itemSchema' => [
                        'question' => [
                            'required' => true,
                            'maxLength' => 255,
                        ],
                        'answer' => [
                            'required' => true,
                            'maxLength' => 2000,
                            'isHtml' => true,
                        ],
                    ],
                ],
            ]
        ));

        // 11. Spacer
        $this->register(new ComponentDefinition(
            type: 'Spacer',
            label: 'Spacer',
            category: 'layout',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'size' => [
                    'type' => 'string',
                    'enum' => ['small', 'medium', 'large', 'xlarge'],
                    'default' => 'medium',
                ],
            ]
        ));

        // 12. Divider
        $this->register(new ComponentDefinition(
            type: 'Divider',
            label: 'Divider',
            category: 'layout',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'style' => [
                    'type' => 'string',
                    'enum' => ['solid', 'dashed', 'dotted'],
                    'default' => 'solid',
                ],
                'color' => [
                    'type' => 'string',
                    'enum' => ['muted', 'primary', 'subtle'],
                    'default' => 'muted',
                ],
            ]
        ));
    }
}
