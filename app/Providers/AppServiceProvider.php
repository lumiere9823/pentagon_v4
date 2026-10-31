<?php

namespace App\Providers;

use App\Domains\Websites\Contracts\HomepageManagerInterface;
use App\Domains\Websites\Contracts\WebsiteProvisionerInterface;
use App\Domains\Websites\Services\HomepageManager;
use App\Domains\Websites\Services\WebsiteProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebsiteProvisionerInterface::class, WebsiteProvisioner::class);
        $this->app->bind(HomepageManagerInterface::class, HomepageManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
