<?php

namespace App\Filament\Partner\Pages;

use App\Models\Agreement;
use App\Models\Subscriber;
use Filament\Pages\Page;

/**
 * Read-only page showing the partner's agreement (contract, pricing,
 * quotas). Rendered as a custom Blade view for a cleaner "card" feel than
 * a form in readonly mode.
 */
class MyAccount extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Mon compte';
    protected static ?string $navigationLabel = 'Mon contrat';
    protected static ?string $title = 'Mon contrat SOS-Expat';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.partner.pages.my-account';

    public ?Agreement $agreement = null;
    public int $activeSubscribersCount = 0;

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user?->partner_firebase_id) {
            abort(403);
        }
        $this->agreement = Agreement::where('partner_firebase_id', $user->partner_firebase_id)->first();
        $this->activeSubscribersCount = Subscriber::where('partner_firebase_id', $user->partner_firebase_id)
            ->where('status', 'active')
            ->count();
    }

    public function getViewData(): array
    {
        return [
            'agreement' => $this->agreement,
            'activeSubscribersCount' => $this->activeSubscribersCount,
        ];
    }
}
