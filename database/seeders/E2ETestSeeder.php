<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('E2ETestSeeder may only run in the testing environment.');
        }

        User::query()->updateOrCreate(
            ['email' => 'e2e-owner@example.test'],
            [
                'name' => 'E2E Owner',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $this->call(StarterTemplateSeeder::class);
    }
}
