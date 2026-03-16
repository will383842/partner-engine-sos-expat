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
}
