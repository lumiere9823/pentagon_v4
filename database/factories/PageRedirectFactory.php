<?php

namespace Database\Factories;

use App\Models\PageRedirect;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageRedirect>
 */
class PageRedirectFactory extends Factory
{
    protected $model = PageRedirect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'source_slug' => fake()->unique()->slug(),
            'target_slug' => fake()->slug(),
            'status_code' => 301,
            'is_active' => true,
        ];
    }
}
