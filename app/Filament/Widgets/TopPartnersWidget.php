<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartnerResource;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPartnersWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widget.top_partners.heading');
    }

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
                    ->label(fn() => __('admin.widget.top_partners.col_partner'))
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->label(fn() => __('admin.widget.top_partners.col_clients'))
                    ->counts('subscribers')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('billing_rate')
                    ->label(fn() => __('admin.widget.top_partners.col_rate'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR'),
                Tables\Columns\TextColumn::make('revenue_12m')
                    ->label(fn() => __('admin.widget.top_partners.col_revenue_12m'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('pending_amount')
                    ->label(fn() => __('admin.widget.top_partners.col_unpaid'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->color(fn($record) => ((float) $record->pending_amount) > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('billing_email')
                    ->label(fn() => __('admin.widget.top_partners.col_contact'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(fn() => __('admin.common.detail'))
                    ->url(fn($record) => PartnerResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
