<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ProductionHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:production-health-check {--json : Output health check results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive production readiness, database, storage, and security health diagnostics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [];
        $overallHealthy = true;

        // 1. Database Connectivity & Response Time
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbDurationMs = round((microtime(true) - $dbStart) * 1000, 2);
            $checks['database'] = [
                'status' => 'OK',
                'latency_ms' => $dbDurationMs,
                'connection' => config('database.default'),
            ];
        } catch (Throwable $e) {
            $overallHealthy = false;
            $checks['database'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
        }

        // 2. Pending Migrations Check
        try {
            $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
            $migrated = DB::table('migrations')->pluck('migration')->all();
            $pendingMigrations = [];

            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');
                if (! in_array($migrationName, $migrated, true)) {
                    $pendingMigrations[] = $migrationName;
                }
            }

            if (empty($pendingMigrations)) {
                $checks['migrations'] = [
                    'status' => 'OK',
                    'pending_count' => 0,
                ];
            } else {
                $overallHealthy = false;
                $checks['migrations'] = [
                    'status' => 'FAILED',
                    'pending_count' => count($pendingMigrations),
                    'pending' => $pendingMigrations,
                ];
            }
        } catch (Throwable $e) {
            $overallHealthy = false;
            $checks['migrations'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
        }

        // 3. Writable Directories Check
        $directoriesToCheck = [
            'storage_app' => storage_path('app'),
            'storage_cache' => storage_path('framework/cache'),
            'storage_sessions' => storage_path('framework/sessions'),
            'storage_views' => storage_path('framework/views'),
            'storage_logs' => storage_path('logs'),
            'bootstrap_cache' => base_path('bootstrap/cache'),
        ];

        $directoryErrors = [];
        foreach ($directoriesToCheck as $name => $path) {
            if (! File::exists($path)) {
                @File::makeDirectory($path, 0775, true);
            }
            if (! is_writable($path)) {
                $directoryErrors[] = $name.' ('.$path.') is not writable';
            }
        }

        if (empty($directoryErrors)) {
            $checks['storage_permissions'] = [
                'status' => 'OK',
                'directories_verified' => count($directoriesToCheck),
            ];
        } else {
            $overallHealthy = false;
            $checks['storage_permissions'] = [
                'status' => 'FAILED',
                'errors' => $directoryErrors,
            ];
        }

        // 4. Production Security & Environment Flags
        $env = config('app.env');
        $debug = config('app.debug');
        $appKey = config('app.key');

        $securityIssues = [];
        if (empty($appKey)) {
            $securityIssues[] = 'APP_KEY is missing or empty';
        }
        if ($env === 'production' && $debug === true) {
            $securityIssues[] = 'APP_DEBUG is enabled in production environment';
        }

        if (empty($securityIssues)) {
            $checks['security_configuration'] = [
                'status' => 'OK',
                'environment' => $env,
                'debug_mode' => $debug ? 'enabled' : 'disabled',
            ];
        } else {
            $overallHealthy = false;
            $checks['security_configuration'] = [
                'status' => 'FAILED',
                'issues' => $securityIssues,
            ];
        }

        // 5. Cache Store Accessibility
        try {
            $cacheKey = 'health_check_probe_'.time();
            Cache::put($cacheKey, 'ok', 10);
            $retrieved = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            if ($retrieved === 'ok') {
                $checks['cache'] = [
                    'status' => 'OK',
                    'store' => config('cache.default'),
                ];
            } else {
                $overallHealthy = false;
                $checks['cache'] = [
                    'status' => 'FAILED',
                    'error' => 'Cache store write/read mismatch',
                ];
            }
        } catch (Throwable $e) {
            $overallHealthy = false;
            $checks['cache'] = [
                'status' => 'FAILED',
                'error' => $e->getMessage(),
            ];
        }

        // Render Output
        if ($this->option('json')) {
            $json = json_encode([
                'healthy' => $overallHealthy,
                'timestamp' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->output->writeln($json ?: '{}');
        } else {
            $this->line('');
            $this->info('=== Pentagon Page Builder Production Health Check ===');
            $this->line('');

            $rows = [];
            foreach ($checks as $name => $details) {
                $statusFormatted = $details['status'] === 'OK'
                    ? '<info>PASS</info>'
                    : '<error>FAIL</error>';
                $detailsText = $details['status'] === 'OK'
                    ? json_encode($details, JSON_UNESCAPED_SLASHES)
                    : ($details['error'] ?? json_encode($details, JSON_UNESCAPED_SLASHES));
                $rows[] = [$name, $statusFormatted, $detailsText];
            }

            $this->table(['Check Name', 'Status', 'Diagnostic Details'], $rows);
            $this->line('');

            if ($overallHealthy) {
                $this->info('All production readiness checks passed successfully.');
            } else {
                $this->error('One or more production health checks failed.');
            }
        }

        return $overallHealthy ? Command::SUCCESS : Command::FAILURE;
    }
}
