<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartnerResource;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top partners by revenue this year.
 *
 * Admin can quickly see who the biggest customers are and prioritise
 * relationship management + retention actions.
 */
class TopPartnersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 10 partenaires par revenu (12 derniers mois)';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Agreement::query()
            ->where('sos_call_active', true)
            ->addSelect([
                'revenue_12m' => PartnerInvoice::selectRaw('COALESCE(SUM(total_amount), 0)')
                    ->whereColumn('agreement_id', 'agreements.id')
                    ->where('status', 'paid')
                    ->where('created_at', '>=', now()->subMonths(12)),
                'pending_amount' => PartnerInvoice::selectRaw('COALESCE(SUM(total_amount), 0)')
                    ->whereColumn('agreement_id', 'agreements.id')
                    ->whereIn('status', ['pending', 'overdue']),
            ])
            ->orderByDesc('revenue_12m')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('partner_name')
                    ->label('Partenaire')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->label('Clients')
                    ->counts('subscribers')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('billing_rate')
                    ->label('Tarif/mois')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR'),
                Tables\Columns\TextColumn::make('revenue_12m')
                    ->label('Revenu 12 mois')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('pending_amount')
                    ->label('Impayé')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->color(fn($record) => ((float) $record->pending_amount) > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('billing_email')
                    ->label('Contact')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Détail')
                    ->url(fn($record) => PartnerResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
