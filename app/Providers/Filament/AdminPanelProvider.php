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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('SOS-Expat Admin')
            ->darkMode(true)
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Red,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            // Filament 3 refuse icône-sur-groupe + icône-sur-items dans le même groupe.
            // Les resources gardent leurs icônes (plus granulaire), les groupes restent texte.
            ->navigationGroups([
                NavigationGroup::make()->label('Dashboard'),
                NavigationGroup::make()->label('Partenaires'),
                NavigationGroup::make()->label('Facturation SOS-Call'),
                NavigationGroup::make()->label('Surveillance'),
                NavigationGroup::make()->label('Configuration'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Filament default — shows "Hello, <user>" card
                Widgets\AccountWidget::class,
                // Stats row (numeric KPIs)
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\ProviderHoldsWidget::class,
                // Charts
                \App\Filament\Widgets\RevenueChartWidget::class,
                \App\Filament\Widgets\PartnerRevenueBreakdownWidget::class,
                // Tables
                \App\Filament\Widgets\TopPartnersWidget::class,
                \App\Filament\Widgets\PendingInvoicesWidget::class,
                \App\Filament\Widgets\OverdueInvoicesWidget::class,
                \App\Filament\Widgets\RecentCallsWidget::class,
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
