<?php

use Database\Seeders\StarterTemplateSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('media:prune-unreferenced --hours=24')->daily();
Schedule::command('media:prune-unreferenced --watchdog-only')->hourly();
Schedule::command('releases:prune-superseded')->daily();
Schedule::command('releases:reconcile')->everyThirtyMinutes();

Artisan::command('templates:seed {--force : Overwrite existing global starter templates with default blueprints}', function () {
    $force = (bool) $this->option('force');
    $seeder = app(StarterTemplateSeeder::class);
    $seeder->run($force);
    $this->info($force ? 'Starter templates seeded (forced overwrite).' : 'Starter templates seeded (non-destructive).');
})->purpose('Seed starter page templates non-destructively or with explicit overwrite');
