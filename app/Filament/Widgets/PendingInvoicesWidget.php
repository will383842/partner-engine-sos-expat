<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartnerInvoiceResource;
use App\Models\PartnerInvoice;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pending invoices (not yet paid, not yet overdue).
 * Gives admin visibility on upcoming cash flow.
 */
class PendingInvoicesWidget extends BaseWidget
{
    protected static ?string $heading = 'Factures en attente de paiement';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return PartnerInvoice::query()
            ->where('status', 'pending')
            ->orderBy('due_date', 'asc');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N°')
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label('Partenaire')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')->label('Période'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Montant')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date()
                    ->color(fn($record) => now()->diffInDays($record->due_date, false) < 3 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('days_until_due')
                    ->label('Reste')
                    ->state(function ($record) {
                        $days = (int) now()->diffInDays($record->due_date, false);
                        return $days >= 0 ? "{$days} j" : 'Échu';
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Marquer payée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Select::make('paid_via')
                            ->options([
                                'stripe' => 'Stripe',
                                'sepa' => 'Virement SEPA',
                                'manual' => 'Manuel',
                            ])
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('payment_note')
                            ->label('Note (optionnel)'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markPaid($data['paid_via'], $data['payment_note'] ?? null);
                        Notification::make()->title('Facture marquée payée')->success()->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->label('Voir')
                    ->url(fn($record) => PartnerInvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25]);
    }
}
