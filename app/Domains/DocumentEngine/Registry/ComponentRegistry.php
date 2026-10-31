<?php

namespace App\Domains\DocumentEngine\Registry;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Schema\ComponentDefinition;
use App\Domains\DocumentEngine\Schema\StylePrimitiveCatalog;

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

    /**
     * Authoritative map of responsive overridable presentation properties per component type.
     * Note: 'display' ('visible' | 'hidden') is permitted across all components.
     *
     * @var array<string, list<string>>
     */
    public const RESPONSIVE_OVERRIDABLE_PROPS = [
        'Section' => ['padding', 'background'],
        'Container' => ['maxWidth', 'centered'],
        'Heading' => ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
        'RichText' => ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
        'Image' => ['aspectRatio'],
        'Button' => ['size', 'variant'],
        'Hero' => ['align', 'background'],
        'Columns' => ['count', 'gap'],
        'CardGrid' => ['columns', 'gap'],
        'FAQ' => [],
        'Spacer' => ['size'],
        'Divider' => ['style', 'color'],
    ];

    public const RESPONSIVE_BREAKPOINTS = [
        'tablet' => 768,
        'desktop' => 1024,
    ];

    public function __construct()
    {
        $this->registerCoreComponents();
    }

    public function isResponsiveOverrideAllowed(string $type, string $prop): bool
    {
        $normalized = $this->normalizeType($type);
        if ($prop === 'display') {
            return true;
        }

        if (! in_array($prop, self::RESPONSIVE_OVERRIDABLE_PROPS[$normalized] ?? [], true)) {
            return false;
        }

        $schema = $this->definitions[$normalized]->propSchemas[$prop] ?? null;
        if (! is_array($schema)) {
            return false;
        }

        $primitive = $schema['stylePrimitive'] ?? null;

        return ! is_string($primitive) || StylePrimitiveCatalog::isResponsive($primitive);
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
            $responsiveProps = array_values(array_filter(
                self::RESPONSIVE_OVERRIDABLE_PROPS[$type] ?? [],
                fn (string $prop): bool => $this->isResponsiveOverrideAllowed($type, $prop),
            ));
            $meta[$type] = $def->toMetadata($responsiveProps);
        }

        return $meta;
    }

    /**
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * @return array{breakpoints: array{tablet: int, desktop: int}, overridableProps: array<string, list<string>>}
     */
    public function getResponsiveContract(): array
    {
        $overridableProps = [];
        foreach (self::RESPONSIVE_OVERRIDABLE_PROPS as $type => $props) {
            $overridableProps[$type] = array_values(array_filter(
                $props,
                fn (string $prop): bool => $this->isResponsiveOverrideAllowed($type, $prop),
            ));
        }

        return [
            'breakpoints' => self::RESPONSIVE_BREAKPOINTS,
            'overridableProps' => $overridableProps,
        ];
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
                    'stylePrimitive' => 'spacing.sectionPadding',
                    'inspector' => ['label' => 'Padding', 'group' => 'style', 'control' => 'select'],
                ],
                'background' => [
                    'type' => 'string',
                    'enum' => ['transparent', 'muted', 'primary', 'dark'],
                    'default' => 'transparent',
                    'stylePrimitive' => 'color.background',
                    'inspector' => ['label' => 'Background', 'group' => 'style', 'control' => 'select'],
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
                    'stylePrimitive' => 'dimensions.maxWidth',
                    'inspector' => ['label' => 'Max Width', 'group' => 'style', 'control' => 'select'],
                ],
                'centered' => [
                    'type' => 'boolean',
                    'default' => true,
                    'inspector' => ['label' => 'Centered', 'group' => 'style', 'control' => 'toggle'],
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
                    'inspector' => ['label' => 'Text', 'group' => 'content', 'control' => 'text'],
                ],
                'level' => [
                    'type' => 'integer',
                    'required' => true,
                    'enum' => [1, 2, 3, 4, 5, 6],
                    'default' => 2,
                    'inspector' => ['label' => 'Level', 'group' => 'style', 'control' => 'select'],
                ],
                'align' => [
                    'type' => 'string',
                    'enum' => ['left', 'center', 'right'],
                    'default' => 'left',
                    'stylePrimitive' => 'typography.align',
                    'inspector' => ['label' => 'Align', 'group' => 'style', 'control' => 'select'],
                ],
                'blockSpacing' => [
                    'type' => 'string',
                    'enum' => ['none', 'small', 'medium', 'large'],
                    'default' => 'small',
                    'stylePrimitive' => 'spacing.block',
                    'inspector' => ['label' => 'Block Spacing', 'group' => 'style', 'control' => 'select'],
                ],
                'maxWidth' => [
                    'type' => 'string',
                    'enum' => ['sm', 'md', 'lg', 'xl', 'full'],
                    'default' => 'full',
                    'stylePrimitive' => 'dimensions.maxWidth',
                    'inspector' => ['label' => 'Max Width', 'group' => 'style', 'control' => 'select'],
                ],
                'fontSize' => [
                    'type' => 'string',
                    'enum' => ['semantic', 'small', 'medium', 'large', 'xlarge'],
                    'default' => 'semantic',
                    'stylePrimitive' => 'typography.fontSize',
                    'inspector' => ['label' => 'Font Size', 'group' => 'style', 'control' => 'select'],
                ],
                'textColor' => [
                    'type' => 'string',
                    'enum' => ['foreground', 'soft', 'muted', 'primary'],
                    'default' => 'foreground',
                    'stylePrimitive' => 'color.foreground',
                    'inspector' => ['label' => 'Text Color', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Content', 'group' => 'content', 'control' => 'textarea'],
                ],
                'align' => [
                    'type' => 'string',
                    'enum' => ['left', 'center', 'right'],
                    'default' => 'left',
                    'stylePrimitive' => 'typography.align',
                    'inspector' => ['label' => 'Align', 'group' => 'style', 'control' => 'select'],
                ],
                'blockSpacing' => [
                    'type' => 'string',
                    'enum' => ['none', 'small', 'medium', 'large'],
                    'default' => 'small',
                    'stylePrimitive' => 'spacing.block',
                    'inspector' => ['label' => 'Block Spacing', 'group' => 'style', 'control' => 'select'],
                ],
                'maxWidth' => [
                    'type' => 'string',
                    'enum' => ['sm', 'md', 'lg', 'xl', 'full'],
                    'default' => 'full',
                    'stylePrimitive' => 'dimensions.maxWidth',
                    'inspector' => ['label' => 'Max Width', 'group' => 'style', 'control' => 'select'],
                ],
                'fontSize' => [
                    'type' => 'string',
                    'enum' => ['semantic', 'small', 'medium', 'large', 'xlarge'],
                    'default' => 'semantic',
                    'stylePrimitive' => 'typography.fontSize',
                    'inspector' => ['label' => 'Font Size', 'group' => 'style', 'control' => 'select'],
                ],
                'textColor' => [
                    'type' => 'string',
                    'enum' => ['foreground', 'soft', 'muted', 'primary'],
                    'default' => 'soft',
                    'stylePrimitive' => 'color.foreground',
                    'inspector' => ['label' => 'Text Color', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Source', 'group' => 'content', 'control' => 'media'],
                ],
                'alt' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 255,
                    'inspector' => ['label' => 'Alt Text', 'group' => 'content', 'control' => 'text'],
                ],
                'caption' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 255,
                    'inspector' => ['label' => 'Caption', 'group' => 'content', 'control' => 'text'],
                ],
                'aspectRatio' => [
                    'type' => 'string',
                    'enum' => ['auto', '16:9', '4:3', '1:1'],
                    'default' => 'auto',
                    'stylePrimitive' => 'dimensions.aspectRatio',
                    'inspector' => ['label' => 'Aspect Ratio', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Label', 'group' => 'content', 'control' => 'text'],
                ],
                'url' => [
                    'type' => 'string',
                    'required' => true,
                    'maxLength' => DocumentLimits::MAX_URL_LENGTH,
                    'isUrl' => true,
                    'inspector' => ['label' => 'URL', 'group' => 'content', 'control' => 'url'],
                ],
                'variant' => [
                    'type' => 'string',
                    'enum' => ['primary', 'secondary', 'outline', 'ghost'],
                    'default' => 'primary',
                    'inspector' => ['label' => 'Variant', 'group' => 'style', 'control' => 'select'],
                ],
                'size' => [
                    'type' => 'string',
                    'enum' => ['sm', 'md', 'lg'],
                    'default' => 'md',
                    'stylePrimitive' => 'dimensions.controlSize',
                    'inspector' => ['label' => 'Size', 'group' => 'style', 'control' => 'select'],
                ],
                'target' => [
                    'type' => 'string',
                    'enum' => ['_self', '_blank'],
                    'default' => '_self',
                    'inspector' => ['label' => 'Target', 'group' => 'settings', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Title', 'group' => 'content', 'control' => 'text'],
                ],
                'subtitle' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 500,
                    'inspector' => ['label' => 'Subtitle', 'group' => 'content', 'control' => 'text'],
                ],
                'align' => [
                    'type' => 'string',
                    'enum' => ['left', 'center', 'right'],
                    'default' => 'center',
                    'stylePrimitive' => 'typography.align',
                    'inspector' => ['label' => 'Align', 'group' => 'style', 'control' => 'select'],
                ],
                'background' => [
                    'type' => 'string',
                    'enum' => ['transparent', 'muted', 'primary', 'dark'],
                    'default' => 'transparent',
                    'stylePrimitive' => 'color.background',
                    'inspector' => ['label' => 'Background', 'group' => 'style', 'control' => 'select'],
                ],
                'ctaText' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => 100,
                    'inspector' => ['label' => 'CTA Text', 'group' => 'content', 'control' => 'text'],
                ],
                'ctaUrl' => [
                    'type' => 'string',
                    'default' => '',
                    'maxLength' => DocumentLimits::MAX_URL_LENGTH,
                    'isUrl' => true,
                    'inspector' => ['label' => 'CTA URL', 'group' => 'content', 'control' => 'url'],
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
                    'inspector' => ['label' => 'Count', 'group' => 'style', 'control' => 'select'],
                ],
                'gap' => [
                    'type' => 'string',
                    'enum' => ['small', 'medium', 'large'],
                    'default' => 'medium',
                    'stylePrimitive' => 'spacing.layoutGap',
                    'inspector' => ['label' => 'Gap', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Columns', 'group' => 'style', 'control' => 'select'],
                ],
                'gap' => [
                    'type' => 'string',
                    'enum' => ['small', 'medium', 'large'],
                    'default' => 'medium',
                    'stylePrimitive' => 'spacing.layoutGap',
                    'inspector' => ['label' => 'Gap', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Items', 'group' => 'content', 'control' => 'faq-list'],
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
                    'stylePrimitive' => 'spacing.spacerHeight',
                    'inspector' => ['label' => 'Size', 'group' => 'style', 'control' => 'select'],
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
                    'inspector' => ['label' => 'Style', 'group' => 'style', 'control' => 'select'],
                ],
                'color' => [
                    'type' => 'string',
                    'enum' => ['muted', 'primary', 'subtle'],
                    'default' => 'muted',
                    'stylePrimitive' => 'color.border',
                    'inspector' => ['label' => 'Color', 'group' => 'style', 'control' => 'select'],
                ],
            ]
        ));
    }
}
