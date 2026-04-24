<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartnerInvoiceResource;
use App\Models\PartnerInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueInvoicesWidget extends BaseWidget
{
    protected static ?string $heading = 'Factures en retard';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return PartnerInvoice::query()
            ->where('status', 'overdue')
            ->orderBy('due_date', 'asc');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N° Facture')
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label('Partenaire')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Période'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Montant')
                    ->money(fn($r) => $r->billing_currency ?? 'EUR'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Retard')
                    ->state(fn($r) => now()->diffInDays($r->due_date) . ' jours')
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Voir')
                    ->url(fn($r) => PartnerInvoiceResource::getUrl('view', ['record' => $r])),
            ])
            ->paginated(false);
    }
}
