<?php

namespace Database\Factories;

use App\Models\PageTemplate;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageTemplate>
 */
class PageTemplateFactory extends Factory
{
    protected $model = PageTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().' '.fake()->word().' '.fake()->word();

        return [
            'website_id' => null, // Default to global
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.uniqid(),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['landing', 'business', 'portfolio', 'lead_gen', 'blank']),
            'schema_version' => 1,
            'document_json' => [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'node_section_'.bin2hex(random_bytes(3)).substr(uniqid(), -4),
                    'type' => 'Section',
                    'props' => [
                        'padding' => 'medium',
                        'background' => 'transparent',
                    ],
                    'children' => [
                        [
                            'id' => 'node_container_'.bin2hex(random_bytes(3)).substr(uniqid(), -4),
                            'type' => 'Container',
                            'props' => [
                                'maxWidth' => 'lg',
                            ],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'thumbnail_path' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the template belongs to a website.
     */
    public function forWebsite(Website|int $website): static
    {
        $websiteId = $website instanceof Website ? $website->id : $website;

        return $this->state(fn (array $attributes) => [
            'website_id' => $websiteId,
        ]);
    }

    /**
     * Indicate that the template is a global starter template.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'website_id' => null,
        ]);
    }

    /**
     * Set specific category.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Indicate inactive state.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
