<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\PartnerScopedQuery;
use App\Filament\Partner\Resources\PartnerInvoiceResource\Pages;
use App\Models\PartnerInvoice;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerInvoiceResource extends Resource
{
    use PartnerScopedQuery;

    protected static ?string $model = PartnerInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_billing');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('panel.invoice.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.invoice.plural_label');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user?->partner_firebase_id) return null;
        $count = PartnerInvoice::where('partner_firebase_id', $user->partner_firebase_id)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $user = auth()->user();
        if (!$user?->partner_firebase_id) return null;
        $hasOverdue = PartnerInvoice::where('partner_firebase_id', $user->partner_firebase_id)
            ->where('status', 'overdue')
            ->exists();
        return $hasOverdue ? 'danger' : 'warning';
    }

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'period'];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(fn() => __('panel.invoice.model_label'))
                ->schema([
                    Infolists\Components\TextEntry::make('invoice_number')->label(fn() => __('panel.invoice.number')),
                    Infolists\Components\TextEntry::make('period')->label(fn() => __('panel.invoice.period')),
                    Infolists\Components\TextEntry::make('active_subscribers')->label(fn() => __('panel.invoice.active_subscribers')),
                    Infolists\Components\TextEntry::make('billing_rate')
                        ->label(fn() => __('panel.invoice.billing_rate'))
                        ->money(fn($record) => $record->billing_currency ?? 'EUR'),
                    Infolists\Components\TextEntry::make('monthly_base_fee')
                        ->label(fn() => __('panel.invoice.monthly_base_fee'))
                        ->money(fn($record) => $record->billing_currency ?? 'EUR')
                        ->placeholder(__('panel.common.dash'))
                        ->visible(fn($record) => (float) ($record->monthly_base_fee ?? 0) > 0),
                    Infolists\Components\TextEntry::make('total_amount')
                        ->label(fn() => __('panel.invoice.amount_total'))
                        ->money(fn($record) => $record->billing_currency ?? 'EUR')
                        ->weight('bold')
                        ->size('lg'),
                    Infolists\Components\TextEntry::make('status')
                        ->label(fn() => __('panel.common.status'))
                        ->badge()
                        ->color(fn(string $state) => [
                            'paid' => 'success',
                            'pending' => 'warning',
                            'overdue' => 'danger',
                            'cancelled' => 'gray',
                        ][$state] ?? 'gray')
                        ->formatStateUsing(fn(string $state) => __('panel.common.' . $state)),
                    Infolists\Components\TextEntry::make('due_date')->label(fn() => __('panel.invoice.due_date'))->date(),
                    Infolists\Components\TextEntry::make('paid_at')->label(fn() => __('panel.invoice.paid_at'))->dateTime()->placeholder(__('panel.common.dash')),
                    Infolists\Components\TextEntry::make('paid_via')->label(fn() => __('panel.invoice.paid_via'))->placeholder(__('panel.common.dash')),
                ])
                ->columns(2),

            Infolists\Components\Section::make(fn() => __('panel.invoice.section_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('calls_expert')->label(fn() => __('panel.invoice.calls_expert')),
                    Infolists\Components\TextEntry::make('calls_lawyer')->label(fn() => __('panel.invoice.calls_lawyer')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label(fn() => __('panel.invoice.period'))
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return __('panel.common.dash');
                        [$year, $month] = explode('-', $state);
                        try {
                            $dt = \Carbon\Carbon::createFromDate((int) $year, (int) $month, 1);
                            return $dt->locale(app()->getLocale())->isoFormat('MMMM YYYY');
                        } catch (\Throwable $e) {
                            return $state;
                        }
                    }),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label(fn() => __('panel.invoice.number_short'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('panel.common.copy_invoice'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('active_subscribers')
                    ->label(fn() => __('panel.invoice.active_subs_short'))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(fn() => __('panel.invoice.amount'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(fn() => __('panel.common.status'))
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'overdue',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state) => __('panel.common.' . $state)),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(fn() => __('panel.invoice.due_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(fn() => __('panel.invoice.paid_at'))
                    ->date()
                    ->placeholder(fn() => __('panel.common.dash'))
                    ->toggleable(),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(fn() => __('panel.invoice.filter_status'))
                    ->options([
                        'paid' => __('panel.common.paid'),
                        'pending' => __('panel.common.pending'),
                        'overdue' => __('panel.common.overdue'),
                        'cancelled' => __('panel.common.cancelled'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(fn() => __('panel.activity.action_detail')),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn($record) => !empty($record->pdf_path))
                    ->url(fn($record) => '/api/partner/sos-call/invoices/' . $record->id . '/pdf', shouldOpenInNewTab: true),
                Tables\Actions\Action::make('payOnline')
                    ->label(fn() => __('panel.common.pay_online'))
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending' && !empty($record->stripe_hosted_url))
                    ->url(fn($record) => $record->stripe_hosted_url, shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerInvoices::route('/'),
            'view'  => Pages\ViewPartnerInvoice::route('/{record}'),
        ];
    }
}
