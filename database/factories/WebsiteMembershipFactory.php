<?php

namespace Database\Factories;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteMembership>
 */
class WebsiteMembershipFactory extends Factory
{
    protected $model = WebsiteMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'user_id' => User::factory(),
            'role' => WebsiteRole::EDITOR,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => WebsiteRole::OWNER,
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn () => [
            'role' => WebsiteRole::EDITOR,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => [
            'role' => WebsiteRole::VIEWER,
        ]);
    }
}
