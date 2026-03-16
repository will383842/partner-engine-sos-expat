<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON responses for API requests (no HTML 404/500)
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return true; // API-only app, always JSON
        });
    })->create();
