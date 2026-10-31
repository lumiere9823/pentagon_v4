<?php

namespace Tests\Feature\DocumentEngine;

use App\Domains\DocumentEngine\DocumentLimits;
use Tests\TestCase;

class ComponentRegistryApiTest extends TestCase
{
    public function test_component_registry_api_returns_registry_metadata_and_limits(): void
    {
        $response = $this->getJson(route('api.components.registry'));

        $response->assertOk()
            ->assertJsonStructure([
                'schemaVersion',
                'components',
                'stylePrimitives',
                'aliases',
                'responsive' => [
                    'breakpoints' => ['tablet', 'desktop'],
                    'overridableProps',
                ],
                'limits' => [
                    'maxPayloadBytes',
                    'maxTreeDepth',
                    'maxTotalNodes',
                    'maxChildrenPerNode',
                ],
            ])
            ->assertJson([
                'schemaVersion' => 1,
                'aliases' => [
                    'Rich Text' => 'RichText',
                    'Card Grid' => 'CardGrid',
                ],
                'responsive' => [
                    'breakpoints' => ['tablet' => 768, 'desktop' => 1024],
                    'overridableProps' => [
                        'Heading' => ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
                        'Columns' => ['count', 'gap'],
                        'RichText' => ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
                    ],
                ],
                'limits' => [
                    'maxPayloadBytes' => DocumentLimits::MAX_PAYLOAD_BYTES,
                    'maxTreeDepth' => DocumentLimits::MAX_TREE_DEPTH,
                    'maxTotalNodes' => DocumentLimits::MAX_TOTAL_NODES,
                    'maxChildrenPerNode' => DocumentLimits::MAX_CHILDREN_PER_NODE,
                ],
            ]);

        $components = $response->json('components');
        $this->assertIsArray($components);
        $this->assertCount(12, $components);

        $types = array_column($components, 'type');
        $this->assertContains('Section', $types);
        $this->assertContains('Container', $types);
        $this->assertContains('Heading', $types);
        $this->assertContains('RichText', $types);
        $this->assertContains('Image', $types);
        $this->assertContains('Button', $types);
        $this->assertContains('Hero', $types);
        $this->assertContains('Columns', $types);
        $this->assertContains('CardGrid', $types);
        $this->assertContains('FAQ', $types);
        $this->assertContains('Spacer', $types);
        $this->assertContains('Divider', $types);
    }
}
