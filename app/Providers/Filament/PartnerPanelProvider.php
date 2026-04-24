<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Partner-facing Filament panel.
 *
 * URL: https://partner-engine.sos-expat.com/
 *
 * Every partner company (AXA, Visa, ...) gets a user row with
 * role=partner and partner_firebase_id matching their Agreement.
 * They log in here to manage their subscribers, view their invoices,
 * and see SOS-Call activity — all scoped to their own data via the
 * `BelongsToPartner` Eloquent global scope.
 *
 * Branding: SOS-Expat red/black/white — no dark mode (forced light).
 */
class PartnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('/')
            ->domain('partner-engine.sos-expat.com')
            ->login()
            ->brandName('SOS-Expat · Espace partenaire')
            ->brandLogo('https://sos-expat.com/sos-logo.webp')
            ->brandLogoHeight('2rem')
            ->favicon('https://sos-expat.com/sos-logo.webp')
            // Force light mode — partners expect a clean, professional white UI
            ->darkMode(false)
            ->colors([
                'primary' => Color::Red,
                'danger'  => Color::Rose,
                'gray'    => Color::Slate,
                'info'    => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Pilotage'),
                NavigationGroup::make()->label('Gestion clients'),
                NavigationGroup::make()->label('Facturation'),
                NavigationGroup::make()->label('Mon compte'),
            ])
            ->discoverResources(
                in: app_path('Filament/Partner/Resources'),
                for: 'App\\Filament\\Partner\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Partner/Pages'),
                for: 'App\\Filament\\Partner\\Pages'
            )
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Partner/Widgets'),
                for: 'App\\Filament\\Partner\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Partner\Widgets\StatsPartnerWidget::class,
                \App\Filament\Partner\Widgets\RevenueEvolutionWidget::class,
                \App\Filament\Partner\Widgets\TopSubscribersWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
