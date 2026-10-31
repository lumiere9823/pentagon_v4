<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\Registry\ComponentRegistry;
use App\Domains\DocumentEngine\Schema\ComponentDefinition;
use App\Domains\DocumentEngine\Schema\StylePrimitiveCatalog;
use Tests\TestCase;

class ComponentRegistryTest extends TestCase
{
    private ComponentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ComponentRegistry;
    }

    public function test_registry_contains_exactly_twelve_core_components(): void
    {
        $allowlist = $this->registry->getAllowlist();

        $expected = [
            'Section',
            'Container',
            'Heading',
            'RichText',
            'Image',
            'Button',
            'Hero',
            'Columns',
            'CardGrid',
            'FAQ',
            'Spacer',
            'Divider',
        ];

        $this->assertCount(12, $allowlist);
        foreach ($expected as $comp) {
            $this->assertTrue($this->registry->has($comp), "Component '{$comp}' is missing from allowlist.");
        }
    }

    public function test_aliases_are_normalized_correctly(): void
    {
        $this->assertTrue($this->registry->has('Rich Text'));
        $this->assertEquals('RichText', $this->registry->normalizeType('Rich Text'));

        $this->assertTrue($this->registry->has('Card Grid'));
        $this->assertEquals('CardGrid', $this->registry->normalizeType('Card Grid'));
    }

    public function test_unknown_component_is_rejected(): void
    {
        $this->assertFalse($this->registry->has('ArbitraryPhpRunner'));
        $this->assertFalse($this->registry->has('CustomCodeWidget'));
        $this->assertNull($this->registry->get('MaliciousComponent'));
    }

    public function test_component_hierarchy_rules(): void
    {
        // Container and Section can have children and can be root
        $section = $this->registry->get('Section');
        $this->assertNotNull($section);
        $this->assertTrue($section->allowChildren);
        $this->assertTrue($section->canBeRoot);

        $container = $this->registry->get('Container');
        $this->assertNotNull($container);
        $this->assertTrue($container->allowChildren);
        $this->assertTrue($container->canBeRoot);

        // Heading is a leaf and cannot be root
        $heading = $this->registry->get('Heading');
        $this->assertNotNull($heading);
        $this->assertFalse($heading->allowChildren);
        $this->assertFalse($heading->canBeRoot);

        // RichText is a leaf
        $richText = $this->registry->get('RichText');
        $this->assertNotNull($richText);
        $this->assertFalse($richText->allowChildren);
        $this->assertFalse($richText->canBeRoot);
    }

    public function test_metadata_export_for_editor(): void
    {
        $meta = $this->registry->getMetadata();

        $this->assertCount(12, $meta);
        $this->assertArrayHasKey('Heading', $meta);
        $this->assertEquals('typography', $meta['Heading']['category']);
        $this->assertArrayHasKey('text', $meta['Heading']['props']);
        $this->assertArrayHasKey('level', $meta['Heading']['props']);
    }

    public function test_every_registered_property_has_valid_inspector_metadata(): void
    {
        $expected = [
            'Section' => ['padding' => ['style', 'select'], 'background' => ['style', 'select']],
            'Container' => ['maxWidth' => ['style', 'select'], 'centered' => ['style', 'toggle']],
            'Heading' => ['text' => ['content', 'text'], 'level' => ['style', 'select'], 'align' => ['style', 'select'], 'blockSpacing' => ['style', 'select'], 'maxWidth' => ['style', 'select'], 'fontSize' => ['style', 'select'], 'textColor' => ['style', 'select']],
            'RichText' => ['content' => ['content', 'textarea'], 'align' => ['style', 'select'], 'blockSpacing' => ['style', 'select'], 'maxWidth' => ['style', 'select'], 'fontSize' => ['style', 'select'], 'textColor' => ['style', 'select']],
            'Image' => ['src' => ['content', 'media'], 'alt' => ['content', 'text'], 'caption' => ['content', 'text'], 'aspectRatio' => ['style', 'select']],
            'Button' => ['label' => ['content', 'text'], 'url' => ['content', 'url'], 'variant' => ['style', 'select'], 'size' => ['style', 'select'], 'target' => ['settings', 'select']],
            'Hero' => ['title' => ['content', 'text'], 'subtitle' => ['content', 'text'], 'align' => ['style', 'select'], 'background' => ['style', 'select'], 'ctaText' => ['content', 'text'], 'ctaUrl' => ['content', 'url']],
            'Columns' => ['count' => ['style', 'select'], 'gap' => ['style', 'select']],
            'CardGrid' => ['columns' => ['style', 'select'], 'gap' => ['style', 'select']],
            'FAQ' => ['items' => ['content', 'faq-list']],
            'Spacer' => ['size' => ['style', 'select']],
            'Divider' => ['style' => ['style', 'select'], 'color' => ['style', 'select']],
        ];

        $metadata = $this->registry->getMetadata();
        $responsive = ComponentRegistry::RESPONSIVE_OVERRIDABLE_PROPS;

        foreach ($expected as $type => $properties) {
            $inspectorByKey = [];
            foreach ($metadata[$type]['inspector'] as $property) {
                $inspectorByKey[$property['key']] = $property;
            }

            $this->assertEqualsCanonicalizing(
                [...array_keys($properties), 'display'],
                array_keys($inspectorByKey),
                "{$type} Inspector metadata must cover every registered property exactly once.",
            );

            foreach ($properties as $key => [$group, $control]) {
                $property = $inspectorByKey[$key];
                $this->assertSame("props.{$key}", $property['path']);
                $this->assertSame($group, $property['group']);
                $this->assertSame($control, $property['control']);
                $this->assertSame(in_array($key, $responsive[$type], true), $property['responsive']);
                $this->assertArrayHasKey($key, $metadata[$type]['props']);
            }

            $this->assertSame('styles.*.display', $inspectorByKey['display']['path']);
            $this->assertSame(['visible', 'hidden'], $inspectorByKey['display']['enum']);
            $this->assertTrue($inspectorByKey['display']['responsive']);
        }
    }

    public function test_style_primitives_are_explicit_and_match_the_shared_contract(): void
    {
        $metadata = $this->registry->getMetadata();
        $declared = [];

        foreach ($metadata as $component) {
            foreach ($component['props'] as $key => $schema) {
                if (! isset($schema['stylePrimitive'])) {
                    continue;
                }

                $primitive = StylePrimitiveCatalog::get($schema['stylePrimitive']);
                $this->assertSame($primitive['type'], $schema['type']);
                $this->assertSame($primitive['values'], $schema['enum']);
                $declared[] = $component['type'].'.'.$key;
            }
        }

        $this->assertContains('Heading.fontSize', $declared);
        $this->assertContains('RichText.textColor', $declared);
        $this->assertContains('Hero.background', $declared);
        $this->assertArrayNotHasKey('stylePrimitive', $metadata['Heading']['props']['level']);
    }

    public function test_nonresponsive_primitive_is_removed_from_responsive_contract_and_metadata(): void
    {
        $reflection = new \ReflectionClass(StylePrimitiveCatalog::class);
        $definitions = $reflection->getProperty('definitions');
        $original = StylePrimitiveCatalog::all();
        $modified = $original;
        $modified['dimensions.controlSize']['responsive'] = false;
        $definitions->setValue(null, $modified);

        try {
            $registry = new ComponentRegistry;
            $metadata = $registry->getMetadata();
            $contract = $registry->getResponsiveContract();
            $sizeField = collect($metadata['Button']['inspector'])
                ->firstWhere('key', 'size');

            $this->assertFalse($registry->isResponsiveOverrideAllowed('Button', 'size'));
            $this->assertNotContains('size', $contract['overridableProps']['Button']);
            $this->assertFalse($metadata['Button']['props']['size']['responsive']);
            $this->assertFalse($sizeField['responsive']);
            $this->assertSame(['sm', 'md', 'lg'], $metadata['Button']['props']['size']['enum']);
        } finally {
            $definitions->setValue(null, $original);
        }
    }

    public function test_unknown_style_primitive_ids_fail_closed(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Unknown style primitive 'unknown.primitive'.");

        new ComponentDefinition(
            type: 'Invalid',
            label: 'Invalid',
            category: 'content',
            allowChildren: false,
            canBeRoot: false,
            propSchemas: [
                'appearance' => [
                    'type' => 'string',
                    'enum' => ['safe'],
                    'stylePrimitive' => 'unknown.primitive',
                ],
            ],
        );
    }
}
