<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerInvoiceResource\Pages;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerInvoiceResource extends Resource
{
    protected static ?string $model = PartnerInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Facturation SOS-Call';
    protected static ?string $navigationLabel = 'Factures';
    protected static ?string $modelLabel = 'Facture';
    protected static ?string $pluralModelLabel = 'Factures';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Select::make('agreement_id')
                        ->label('Partenaire')
                        ->options(Agreement::where('sos_call_active', true)->pluck('partner_name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Numéro de facture')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('period')
                        ->label('Période (YYYY-MM)')
                        ->required()
                        ->placeholder('2026-04')
                        ->regex('/^\d{4}-\d{2}$/'),
                ])->columns(3),

            Forms\Components\Section::make('Montants')
                ->schema([
                    Forms\Components\TextInput::make('active_subscribers')
                        ->label('Clients actifs')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('billing_rate')
                        ->label('Tarif unitaire')
                        ->numeric()
                        ->step(0.01)
                        ->required(),
                    Forms\Components\Select::make('billing_currency')
                        ->label('Devise')
                        ->options(['EUR' => 'EUR', 'USD' => 'USD'])
                        ->default('EUR')
                        ->required(),
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Montant total')
                        ->numeric()
                        ->step(0.01)
                        ->required(),
                ])->columns(4),

            Forms\Components\Section::make('Statut et paiement')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'pending' => 'En attente',
                            'paid' => 'Payée',
                            'overdue' => 'En retard',
                            'cancelled' => 'Annulée',
                        ])
                        ->default('pending')
                        ->required(),
                    Forms\Components\DatePicker::make('due_date')
                        ->label('Date d\'échéance')
                        ->required(),
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Date de paiement'),
                    Forms\Components\Select::make('paid_via')
                        ->label('Moyen de paiement')
                        ->options([
                            'stripe' => 'Stripe',
                            'sepa' => 'Virement SEPA',
                            'manual' => 'Manuel',
                        ]),
                ])->columns(2),

            Forms\Components\Section::make('Stripe')
                ->schema([
                    Forms\Components\TextInput::make('stripe_invoice_id')
                        ->label('Stripe Invoice ID')
                        ->disabled(),
                    Forms\Components\TextInput::make('stripe_hosted_url')
                        ->label('URL Stripe hosted')
                        ->disabled(),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N° Facture')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label('Partenaire')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Période')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_subscribers')
                    ->label('Clients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Montant')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'gray' => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Payée le')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'paid' => 'Payée',
                        'overdue' => 'En retard',
                        'cancelled' => 'Annulée',
                    ]),
                Tables\Filters\SelectFilter::make('agreement_id')
                    ->label('Partenaire')
                    ->options(Agreement::where('sos_call_active', true)->pluck('partner_name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markPaid')
                    ->label('Marquer payée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('paid_via')
                            ->label('Moyen de paiement')
                            ->options([
                                'stripe' => 'Stripe',
                                'sepa' => 'Virement SEPA',
                                'manual' => 'Manuel',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('payment_note')
                            ->label('Note de paiement (optionnel)'),
                    ])
                    ->visible(fn($record) => $record->status !== 'paid')
                    ->action(function ($record, array $data) {
                        $record->markPaid($data['paid_via'], $data['payment_note'] ?? null);
                        Notification::make()
                            ->title('Facture marquée payée')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('downloadPdf')
                    ->label('Télécharger PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn($record) => !empty($record->pdf_path))
                    ->url(fn($record) => $record->pdf_path ? '/admin/invoices/' . $record->id . '/pdf' : null, true),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markPaid')
                        ->label('Marquer payées (groupe)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status !== 'paid') {
                                    $record->markPaid('manual', 'Bulk action');
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerInvoices::route('/'),
            'create' => Pages\CreatePartnerInvoice::route('/create'),
            'view' => Pages\ViewPartnerInvoice::route('/{record}'),
            'edit' => Pages\EditPartnerInvoice::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'overdue')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
