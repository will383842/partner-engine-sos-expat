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
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_billing');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.nav.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('admin.invoice.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.invoice.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn() => __('admin.invoice.section_id'))
                ->schema([
                    Forms\Components\Select::make('agreement_id')
                        ->label(fn() => __('admin.invoice.partner'))
                        ->options(Agreement::where('sos_call_active', true)->pluck('partner_name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('invoice_number')
                        ->label(fn() => __('admin.invoice.invoice_number'))
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('period')
                        ->label(fn() => __('admin.invoice.period'))
                        ->required()
                        ->placeholder('2026-04')
                        ->regex('/^\d{4}-\d{2}$/'),
                ])->columns(3),

            Forms\Components\Section::make(fn() => __('admin.invoice.section_amounts'))
                ->schema([
                    Forms\Components\TextInput::make('active_subscribers')
                        ->label(fn() => __('admin.invoice.active_subscribers'))
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('billing_rate')
                        ->label(fn() => __('admin.invoice.billing_rate'))
                        ->numeric()
                        ->step(0.01)
                        ->required(),
                    Forms\Components\TextInput::make('monthly_base_fee')
                        ->label(fn() => __('admin.invoice.monthly_base_fee'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->placeholder('0.00'),
                    Forms\Components\Select::make('billing_currency')
                        ->label(fn() => __('admin.invoice.billing_currency'))
                        ->options(['EUR' => 'EUR', 'USD' => 'USD'])
                        ->default('EUR')
                        ->required(),
                    Forms\Components\TextInput::make('total_amount')
                        ->label(fn() => __('admin.invoice.total_amount'))
                        ->numeric()
                        ->step(0.01)
                        ->required(),
                ])->columns(4),

            Forms\Components\Section::make(fn() => __('admin.invoice.section_status'))
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label(fn() => __('admin.common.status'))
                        ->options([
                            'pending' => __('admin.common.pending'),
                            'paid' => __('admin.common.paid'),
                            'overdue' => __('admin.common.overdue'),
                            'cancelled' => __('admin.common.cancelled'),
                        ])
                        ->default('pending')
                        ->required(),
                    Forms\Components\DatePicker::make('due_date')
                        ->label(fn() => __('admin.invoice.due_date'))
                        ->required(),
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label(fn() => __('admin.invoice.paid_at')),
                    Forms\Components\Select::make('paid_via')
                        ->label(fn() => __('admin.invoice.paid_via'))
                        ->options([
                            'stripe' => __('admin.common.pay_stripe'),
                            'sepa' => __('admin.common.pay_sepa'),
                            'manual' => __('admin.common.pay_manual'),
                        ]),
                ])->columns(2),

            Forms\Components\Section::make(fn() => __('admin.invoice.section_stripe'))
                ->schema([
                    Forms\Components\TextInput::make('stripe_invoice_id')
                        ->label(fn() => __('admin.invoice.stripe_id'))
                        ->disabled(),
                    Forms\Components\TextInput::make('stripe_hosted_url')
                        ->label(fn() => __('admin.invoice.stripe_url'))
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
                    ->label(fn() => __('admin.invoice.invoice_number_short'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('admin.common.copy_invoice')),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label(fn() => __('admin.invoice.partner'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->label(fn() => __('admin.invoice.period_short'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_subscribers')
                    ->label(fn() => __('admin.invoice.active_subs_short'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(fn() => __('admin.invoice.amount'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state) => __('admin.common.' . $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(fn() => __('admin.invoice.due_date_short'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(fn() => __('admin.invoice.paid_short'))
                    ->dateTime()
                    ->placeholder(fn() => __('admin.common.dash'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->options([
                        'pending' => __('admin.common.pending'),
                        'paid' => __('admin.common.paid'),
                        'overdue' => __('admin.common.overdue'),
                        'cancelled' => __('admin.common.cancelled'),
                    ]),
                Tables\Filters\SelectFilter::make('agreement_id')
                    ->label(fn() => __('admin.invoice.partner'))
                    ->options(Agreement::where('sos_call_active', true)->pluck('partner_name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markPaid')
                    ->label(fn() => __('admin.invoice.action_mark_paid'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('paid_via')
                            ->label(fn() => __('admin.invoice.paid_via'))
                            ->options([
                                'stripe' => __('admin.common.pay_stripe'),
                                'sepa' => __('admin.common.pay_sepa'),
                                'manual' => __('admin.common.pay_manual'),
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('payment_note')
                            ->label(fn() => __('admin.invoice.mark_paid_note')),
                    ])
                    ->visible(fn($record) => $record->status !== 'paid')
                    ->action(function ($record, array $data) {
                        $record->markPaid($data['paid_via'], $data['payment_note'] ?? null);
                        Notification::make()
                            ->title(__('admin.invoice.mark_paid_done'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('downloadPdf')
                    ->label(fn() => __('admin.invoice.action_download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn($record) => !empty($record->pdf_path))
                    ->url(fn($record) => $record->pdf_path ? '/admin/invoices/' . $record->id . '/pdf' : null, true),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markPaid')
                        ->label(fn() => __('admin.invoice.action_mark_paid_bulk'))
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
