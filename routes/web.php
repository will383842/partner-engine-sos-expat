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

// ⚠️ No generic '/' route here — that would collide with the partner
// Filament panel mounted at path='/' on partner-engine.sos-expat.com.
// Each host gets its own explicit domain-scoped block below.

// admin.sos-expat.com: just redirect root to /admin (Filament admin panel)
Route::domain('admin.sos-expat.com')->group(function () {
    Route::get('/', fn() => redirect('/admin'))->name('admin.home');
});

// sos-call.sos-expat.com + legacy hosts: serve the SOS-Call Blade landing
Route::domain('sos-call.sos-expat.com')->group(function () {
    Route::get('/', [SosCallWebController::class, 'index'])->name('sos-call.home');
});

// partner-engine.sos-expat.com is intentionally NOT listed here — the
// PartnerPanelProvider owns all routes on that host (login, dashboard,
// resources, etc.). Laravel will match Filament's routes directly.

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
