<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes — Partner Engine
|--------------------------------------------------------------------------
|
| Named rate limiters (webhook, partner, admin, subscriber, partner-api,
| sos-call-check) are registered in App\Providers\RateLimitServiceProvider
| so they work correctly with route:cache.
|
*/

// Health check (public, no auth)
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);

// Webhooks (secured by X-Engine-Secret + rate limited 10/min)
Route::prefix('webhooks')->middleware(['webhook.secret', 'throttle:webhook'])->group(function () {
    Route::post('/call-completed', [\App\Http\Controllers\Webhook\WebhookController::class, 'callCompleted']);
    Route::post('/subscriber-registered', [\App\Http\Controllers\Webhook\WebhookController::class, 'subscriberRegistered']);
});

// Stripe webhook (signature validated inside controller — no middleware)
Route::post('/webhooks/stripe/invoice-events', [\App\Http\Controllers\Webhook\StripeWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');

// SOS-Call public endpoint (rate-limited, no auth — code OR phone+email verification)
Route::prefix('sos-call')->group(function () {
    Route::post('/check', [\App\Http\Controllers\SosCallController::class, 'check'])
        ->middleware('throttle:sos-call-check');
});

// SOS-Call webhook endpoints (called by Firebase Functions — secured by X-Engine-Secret)
Route::prefix('sos-call')->middleware(['webhook.secret', 'throttle:webhook'])->group(function () {
    Route::post('/check-session', [\App\Http\Controllers\SosCallController::class, 'checkSession']);
    Route::post('/log', [\App\Http\Controllers\SosCallController::class, 'log']);
});

// Partner routes (Firebase Auth + role=partner + rate limited 60/min)
Route::prefix('partner')->middleware(['firebase.auth', 'require.partner', 'throttle:partner'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Partner\DashboardController::class, 'index']);
    Route::get('/agreement', [\App\Http\Controllers\Partner\AgreementController::class, 'show']);
    Route::get('/activity', [\App\Http\Controllers\Partner\ActivityController::class, 'index']);
    Route::get('/stats', [\App\Http\Controllers\Partner\StatsController::class, 'index']);
    Route::get('/earnings/breakdown', [\App\Http\Controllers\Partner\DashboardController::class, 'earningsBreakdown']);

    // Subscribers CRUD
    Route::get('/subscribers', [\App\Http\Controllers\Partner\SubscriberController::class, 'index']);
    Route::post('/subscribers', [\App\Http\Controllers\Partner\SubscriberController::class, 'store']);
    Route::post('/subscribers/import', [\App\Http\Controllers\Partner\SubscriberController::class, 'import']);
    Route::get('/subscribers/export', [\App\Http\Controllers\Partner\SubscriberController::class, 'export']);
    Route::get('/subscribers/{id}', [\App\Http\Controllers\Partner\SubscriberController::class, 'show']);
    Route::put('/subscribers/{id}', [\App\Http\Controllers\Partner\SubscriberController::class, 'update']);
    Route::delete('/subscribers/{id}', [\App\Http\Controllers\Partner\SubscriberController::class, 'destroy']);
    Route::post('/subscribers/{id}/resend-invitation', [\App\Http\Controllers\Partner\SubscriberController::class, 'resendInvitation']);

    // SOS-Call activity & invoices (partner dashboard)
    Route::prefix('sos-call')->group(function () {
        Route::get('/activity/kpis', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'kpis']);
        Route::get('/activity/timeline', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'timeline']);
        Route::get('/activity/breakdown', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'breakdown']);
        Route::get('/activity/hierarchy', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'hierarchy']);
        Route::get('/activity/top-subscribers', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'topSubscribers']);
        Route::get('/activity/calls', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'callsHistory']);
        Route::get('/activity/export', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'exportCsv']);
        Route::get('/invoices', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'invoices']);
        Route::get('/invoices/{id}', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'showInvoice']);
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Partner\PartnerSosCallController::class, 'downloadInvoicePdf']);
    });
});

// Subscriber self-service routes (Firebase Auth + linked subscriber + rate limited 60/min)
Route::prefix('subscriber')->middleware(['firebase.auth', 'require.subscriber', 'throttle:subscriber'])->group(function () {
    Route::get('/me', [\App\Http\Controllers\Subscriber\SubscriberSelfController::class, 'me']);
    Route::get('/activity', [\App\Http\Controllers\Subscriber\SubscriberSelfController::class, 'activity']);
});

// Admin routes (Firebase Auth + role=admin + rate limited 120/min)
Route::prefix('admin')->middleware(['firebase.auth', 'require.admin', 'throttle:admin'])->group(function () {
    // Partners
    Route::get('/partners', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'index']);
    Route::get('/partners/{id}', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'show']);
    Route::get('/partners/{id}/activity', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'activity']);
    Route::get('/stats', [\App\Http\Controllers\Admin\StatsAdminController::class, 'index']);

    // Agreements
    Route::post('/partners/{id}/agreements', [\App\Http\Controllers\Admin\AgreementAdminController::class, 'store']);
    Route::get('/partners/{id}/agreements/{agreementId}', [\App\Http\Controllers\Admin\AgreementAdminController::class, 'show']);
    Route::put('/partners/{id}/agreements/{agreementId}', [\App\Http\Controllers\Admin\AgreementAdminController::class, 'update']);
    Route::delete('/partners/{id}/agreements/{agreementId}', [\App\Http\Controllers\Admin\AgreementAdminController::class, 'destroy']);
    Route::post('/partners/{id}/agreements/{agreementId}/renew', [\App\Http\Controllers\Admin\AgreementAdminController::class, 'renew']);

    // Subscribers (admin)
    Route::get('/partners/{id}/subscribers', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'index']);
    Route::put('/partners/{id}/subscribers/{subscriberId}', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'update']);
    Route::post('/partners/{id}/subscribers/{subscriberId}/suspend', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'suspend']);
    Route::post('/partners/{id}/subscribers/{subscriberId}/reactivate', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'reactivate']);
    Route::post('/partners/{id}/subscribers/import', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'import']);
    Route::delete('/partners/{id}/subscribers/bulk', [\App\Http\Controllers\Admin\SubscriberAdminController::class, 'bulkDestroy']);

    // CSV imports
    Route::get('/csv-imports', [\App\Http\Controllers\Admin\CsvImportAdminController::class, 'index']);
    Route::get('/csv-imports/{id}', [\App\Http\Controllers\Admin\CsvImportAdminController::class, 'show']);

    // Email templates
    Route::get('/partners/{id}/email-templates', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'emailTemplates']);
    Route::put('/partners/{id}/email-templates/{type}', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'updateEmailTemplate']);
    Route::delete('/partners/{id}/email-templates/{type}', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'deleteEmailTemplate']);

    // Audit log
    Route::get('/partners/{id}/audit-log', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'auditLog']);
    Route::get('/audit-log', [\App\Http\Controllers\Admin\StatsAdminController::class, 'auditLog']);
});

/*
|--------------------------------------------------------------------------
| Partner server-to-server API (v1) — API key authentication
|--------------------------------------------------------------------------
| For partners that want to automate subscriber provisioning from their own
| CRM / HR system / insurance portal. Authenticate with a static API key:
|   Authorization: Bearer pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
| Or:
|   X-Partner-API-Key: pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
*/
Route::prefix('v1/partner')->middleware(['partner.apikey', 'throttle:partner-api'])->group(function () {
    // Subscribers (requires scope subscribers:read or subscribers:write)
    Route::get('/subscribers', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'index'])
        ->middleware('partner.apikey:subscribers:read');
    Route::get('/subscribers/{id}', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'show'])
        ->middleware('partner.apikey:subscribers:read');
    Route::post('/subscribers', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'store'])
        ->middleware('partner.apikey:subscribers:write');
    Route::post('/subscribers/bulk', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'bulkStore'])
        ->middleware('partner.apikey:subscribers:write');
    Route::patch('/subscribers/{id}', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'update'])
        ->middleware('partner.apikey:subscribers:write');
    Route::delete('/subscribers/{id}', [\App\Http\Controllers\PartnerApi\SubscriberApiController::class, 'destroy'])
        ->middleware('partner.apikey:subscribers:write');
});
