<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes — Partner Engine
|--------------------------------------------------------------------------
*/

// Health check (public, no auth)
Route::get('/health', [HealthController::class, 'index']);

// Webhooks (secured by X-Engine-Secret)
Route::prefix('webhooks')->middleware('webhook.secret')->group(function () {
    Route::post('/call-completed', [\App\Http\Controllers\Webhook\WebhookController::class, 'callCompleted']);
    Route::post('/subscriber-registered', [\App\Http\Controllers\Webhook\WebhookController::class, 'subscriberRegistered']);
});

// Partner routes (Firebase Auth + role=partner)
Route::prefix('partner')->middleware(['firebase.auth', 'require.partner'])->group(function () {
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
});

// Subscriber self-service routes (Firebase Auth + linked subscriber)
Route::prefix('subscriber')->middleware(['firebase.auth', 'require.subscriber'])->group(function () {
    Route::get('/me', [\App\Http\Controllers\Subscriber\SubscriberSelfController::class, 'me']);
    Route::get('/activity', [\App\Http\Controllers\Subscriber\SubscriberSelfController::class, 'activity']);
});

// Admin routes (Firebase Auth + role=admin)
Route::prefix('admin')->middleware(['firebase.auth', 'require.admin'])->group(function () {
    // Partners
    Route::get('/partners', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'index']);
    Route::get('/partners/{id}', [\App\Http\Controllers\Admin\PartnerAdminController::class, 'show']);
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
