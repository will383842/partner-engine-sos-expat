<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\SosCallWebController;
use App\Http\Controllers\Web\SubscriberDashboardController;
use App\Http\Controllers\Subscriber\SubscriberGdprController;

/*
|--------------------------------------------------------------------------
| Web Routes — SOS-Call public page + Subscriber dashboard
|--------------------------------------------------------------------------
|
| Served on sos-call.sos-expat.com (separate Nginx server block).
| Also accessible at sos-expat.com/sos-call via SPA routing (fallback).
|
*/

Route::get('/', [SosCallWebController::class, 'index'])->name('sos-call.home');
Route::get('/sos-call', [SosCallWebController::class, 'index'])->name('sos-call.landing');

// Subscriber dashboard at /mon-acces (magic link auth)
Route::prefix('mon-acces')->group(function () {
    Route::get('/', [SubscriberDashboardController::class, 'dashboard'])->name('subscriber.dashboard');
    Route::get('/login', [SubscriberDashboardController::class, 'showLogin'])->name('subscriber.login');
    Route::post('/magic-link', [SubscriberDashboardController::class, 'sendMagicLink'])->name('subscriber.magic-link');
    Route::get('/auth', [SubscriberDashboardController::class, 'authenticate'])->name('subscriber.auth');
    Route::post('/logout', [SubscriberDashboardController::class, 'logout'])->name('subscriber.logout');

    // RGPD endpoints (Article 15 + Article 17)
    Route::get('/export', [SubscriberGdprController::class, 'export'])->name('subscriber.gdpr.export');
    Route::delete('/delete', [SubscriberGdprController::class, 'delete'])->name('subscriber.gdpr.delete');
});
