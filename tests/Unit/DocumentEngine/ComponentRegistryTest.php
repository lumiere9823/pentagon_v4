<?php

namespace Tests\Unit\DocumentEngine;

use App\Domains\DocumentEngine\Registry\ComponentRegistry;
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
}
