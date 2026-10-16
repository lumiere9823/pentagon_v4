<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Return safe, minimal application health status.
     * Does not expose credentials, SQL errors, memory details, or stack traces.
     */
    public function __invoke(): JsonResponse
    {
        $dbHealthy = false;

        try {
            DB::connection()->getPdo()->query('SELECT 1');
            $dbHealthy = true;
        } catch (Throwable) {
            $dbHealthy = false;
        }

        $statusCode = $dbHealthy ? 200 : 503;

        return response()->json([
            'status' => $dbHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbHealthy ? 'connected' : 'unavailable',
            ],
        ], $statusCode);
    }
}
