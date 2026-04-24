<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'firebase.auth' => \App\Http\Middleware\FirebaseAuth::class,
            'require.partner' => \App\Http\Middleware\RequirePartner::class,
            'require.admin' => \App\Http\Middleware\RequireAdmin::class,
            'require.subscriber' => \App\Http\Middleware\RequireSubscriber::class,
            'webhook.secret' => \App\Http\Middleware\WebhookSecret::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'partner.apikey' => \App\Http\Middleware\PartnerApiKey::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Trust reverse proxy (host Nginx) headers. Without this, Laravel sees
        // request as HTTP from pe-nginx and generates mixed-content URLs
        // (http:// CSS on https:// page) — Filament admin UI breaks.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON responses for API requests only (not for /sos-call Blade pages)
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            // API routes always get JSON
            if ($request->is('api/*') || $request->expectsJson()) {
                return true;
            }
            // Web routes (Blade pages) get HTML responses
            return false;
        });
    })->create();
