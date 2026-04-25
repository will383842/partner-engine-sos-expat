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

// Language switcher (POST /locale/{code}) — sets a cookie, middleware reads it.
Route::post('/locale/{locale}', \App\Http\Controllers\LocaleController::class)
    ->where('locale', '[a-z]{2}')
    ->name('locale.switch');

// ⚠️ No generic '/' route here — that would collide with the partner
// Filament panel mounted at path='/' on partner-engine.sos-expat.com.
// Each host gets its own explicit domain-scoped block below.

// admin.sos-expat.com: just redirect root to /admin (Filament admin panel)
Route::domain('admin.sos-expat.com')->group(function () {
    Route::get('/', fn() => redirect('/admin'))->name('admin.home');
});

// 2FA email verification — gated behind authenticated session, used by the
// Verify2FAEmail middleware to interrupt the login flow when the admin
// has two_factor_email_enabled=true. Routes are mounted under /admin/* so
// they share the admin Filament cookie/session domain.
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('/2fa-verify', [\App\Http\Controllers\TwoFactorEmailController::class, 'show'])->name('admin.2fa.show');
    Route::post('/2fa-verify', [\App\Http\Controllers\TwoFactorEmailController::class, 'verify'])->name('admin.2fa.verify');
    Route::post('/2fa-resend', [\App\Http\Controllers\TwoFactorEmailController::class, 'resend'])->name('admin.2fa.resend');
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
