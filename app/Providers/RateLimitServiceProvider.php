<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all named rate limiters used in routes/api.php.
 *
 * Must live in a service provider (not in routes/api.php) so the limiters
 * are registered even when route:cache is active — cached route files are
 * dehydrated data, not executed code, so RateLimiter::for() calls in
 * routes/api.php would never run when the cache is warm.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('webhook', fn (Request $r) =>
            Limit::perMinute(10)->by($r->ip())
        );

        RateLimiter::for('partner', fn (Request $r) =>
            Limit::perMinute(60)->by($r->attributes->get('firebase_uid', $r->ip()))
        );

        RateLimiter::for('admin', fn (Request $r) =>
            Limit::perMinute(120)->by($r->attributes->get('firebase_uid', $r->ip()))
        );

        RateLimiter::for('subscriber', fn (Request $r) =>
            Limit::perMinute(60)->by($r->attributes->get('firebase_uid', $r->ip()))
        );

        // Partner server-to-server API (higher limits — legitimate automated traffic)
        RateLimiter::for('partner-api', function (Request $r) {
            $key = $r->attributes->get('partner_api_key');
            $id = $key?->id ?: $r->ip();
            return Limit::perMinute(300)->by("partner-api:{$id}");
        });

        // SOS-Call public endpoint:
        //   - 10/min per IP
        //   - 5 attempts per 15min per identifier (code or phone) to block brute-force
        RateLimiter::for('sos-call-check', function (Request $r) {
            $identifier = $r->input('code') ?: $r->input('phone') ?: $r->ip();
            return [
                Limit::perMinute(10)->by($r->ip()),
                Limit::perMinutes(15, 5)->by((string) $identifier),
            ];
        });
    }
}
