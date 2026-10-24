<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'website_id' => Website::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(3),
            'document_json' => null,
            'status' => PageStatus::DRAFT,
            'lock_version' => 1,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $seo
     */
    public function withSeo(?array $seo = null): static
    {
        return $this->state(fn () => [
            'seo_metadata' => $seo ?? [
                'meta_title' => 'Custom SEO Title',
                'meta_description' => 'Custom SEO Description',
                'canonical_url' => 'https://example.com/custom',
                'noindex' => false,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $document
     */
    public function withDocument(?array $document = null): static
    {
        return $this->state(fn () => [
            'document_json' => $document ?? [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root_section_default',
                    'type' => 'Section',
                    'props' => [
                        'padding' => 'medium',
                        'background' => 'transparent',
                    ],
                    'children' => [],
                ],
                'pageSettings' => [
                    'title' => 'Default Page',
                    'description' => '',
                ],
            ],
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PageStatus::PUBLISHED,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => PageStatus::ARCHIVED,
        ]);
    }
}
