<?php

namespace Database\Factories;

use App\Enums\PageReleaseStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageRelease>
 */
class PageReleaseFactory extends Factory
{
    protected $model = PageRelease::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'page_id' => Page::factory(),
            'page_revision_id' => function (array $attributes) {
                return PageRevision::factory()->create([
                    'page_id' => $attributes['page_id'] ?? Page::factory(),
                ])->id;
            },
            'release_number' => 1,
            'status' => PageReleaseStatus::PENDING,
            'schema_version' => 1,
            'renderer_version' => 1,
            'manifest_json' => [
                'release_number' => 1,
                'schema_version' => 1,
                'html_bytes' => 1024,
                'html_hash' => hash('sha256', '<html>test</html>'),
                'media_asset_ids' => [],
            ],
            'directory_path' => 'releases/1/1/r_000001_test',
            'error_message' => null,
            'created_by' => User::factory(),
            'activated_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageReleaseStatus::ACTIVE,
            'activated_at' => now(),
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageReleaseStatus::SUPERSEDED,
            'activated_at' => now()->subDay(),
        ]);
    }

    public function failed(?string $error = 'Render error'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageReleaseStatus::FAILED,
            'error_message' => $error,
            'activated_at' => null,
        ]);
    }

    public function pruned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageReleaseStatus::PRUNED,
            'activated_at' => now()->subDays(7),
        ]);
    }
}
