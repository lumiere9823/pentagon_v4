<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageRevision>
 */
class PageRevisionFactory extends Factory
{
    protected $model = PageRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = [
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
                'title' => fake()->sentence(3),
                'description' => fake()->sentence(6),
            ],
        ];

        return [
            'page_id' => Page::factory(),
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $document,
            'document_hash' => PageRevision::canonicalizeDocument($document),
            'change_summary' => 'Initial version',
            'is_autosave' => false,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
