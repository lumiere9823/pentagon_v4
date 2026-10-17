<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();
        $ext = fake()->randomElement(['jpg', 'png', 'webp', 'gif']);
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        return [
            'website_id' => Website::factory(),
            'storage_disk' => 'public',
            'storage_path' => "media/1/{$uuid}.{$ext}",
            'original_filename' => fake()->word().".{$ext}",
            'mime_type' => $mimeMap[$ext],
            'size_bytes' => fake()->numberBetween(10240, 2097152),
            'width' => fake()->randomElement([640, 800, 1024, 1920]),
            'height' => fake()->randomElement([480, 600, 768, 1080]),
            'alt_text' => fake()->sentence(3),
            'uploaded_by' => User::factory(),
        ];
    }
}
