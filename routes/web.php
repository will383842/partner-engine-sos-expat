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

// Root route is dispatched by Host header (X-Forwarded-Host set by upstream Nginx):
//   admin.sos-expat.com          -> redirect to /admin (Filament login)
//   partner-engine.sos-expat.com -> partner Filament panel (let Filament handle it)
//   sos-call.sos-expat.com + everything else -> SOS-Call Blade landing
Route::get('/', function (\Illuminate\Http\Request $request) {
    $host = strtolower($request->getHost());

    if (str_starts_with($host, 'admin.')) {
        return redirect('/admin');
    }

    // partner-engine.sos-expat.com root is owned by the partner Filament panel
    // (PartnerPanelProvider mounts at path='/' for that domain). If the request
    // reaches this route, we forward it to the panel explicitly.
    if (str_starts_with($host, 'partner-engine.') || str_starts_with($host, 'api.')) {
        return redirect('/login');
    }

    return app(SosCallWebController::class)->index($request);
})->name('sos-call.home');

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
