<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $dbStatus = 'disconnected';
        $redisStatus = 'disconnected';

        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            // DB not available
        }

        try {
            Redis::ping();
            $redisStatus = 'connected';
        } catch (\Exception $e) {
            // Redis not available
        }

        $allOk = $dbStatus === 'connected' && $redisStatus === 'connected';

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'version' => '1.0.0',
            'database' => $dbStatus,
            'redis' => $redisStatus,
            'timestamp' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }

    /**
     * Detailed health report — components + config presence.
     * Used by Filament "Santé système" widget and internal monitoring.
     */
    public function detailed(): JsonResponse
    {
        $components = [];

        try {
            DB::connection()->getPdo();
            $components['database'] = ['status' => 'ok', 'driver' => DB::connection()->getDriverName()];
        } catch (\Throwable $e) {
            $components['database'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        try {
            Redis::ping();
            $components['redis'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $components['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        $components['config'] = [
            'status' => 'ok',
            'app_env' => config('app.env'),
            'app_key_set' => !empty(config('app.key')),
            'stripe_webhook_secret_set' => !empty(config('services.stripe.webhook_secret')),
            'engine_api_key_set' => !empty(config('services.engine.api_key')),
        ];

        $allOk = collect($components)->every(fn($c) => $c['status'] === 'ok');

        return response()->json([
            'service' => 'partner-engine',
            'status' => $allOk ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'components' => $components,
        ], $allOk ? 200 : 503);
    }
}
