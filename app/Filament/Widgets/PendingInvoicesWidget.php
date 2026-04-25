<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartnerInvoiceResource;
use App\Models\PartnerInvoice;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widget.pending.heading');
    }

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
                    ->label(fn() => __('admin.invoice.invoice_number_col'))
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label(fn() => __('admin.invoice.partner'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')->label(fn() => __('admin.invoice.period_short')),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(fn() => __('admin.invoice.amount'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(fn() => __('admin.invoice.due_date_short'))
                    ->date()
                    ->color(fn($record) => now()->diffInDays($record->due_date, false) < 3 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('days_until_due')
                    ->label(fn() => __('admin.widget.pending.col_remaining'))
                    ->state(function ($record) {
                        $days = (int) now()->diffInDays($record->due_date, false);
                        return $days >= 0
                            ? __('admin.widget.pending.days_remaining', ['days' => $days])
                            : __('admin.widget.pending.overdue');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label(fn() => __('admin.invoice.action_mark_paid'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Select::make('paid_via')
                            ->label(fn() => __('admin.invoice.paid_via'))
                            ->options([
                                'stripe' => __('admin.common.pay_stripe'),
                                'sepa' => __('admin.common.pay_sepa'),
                                'manual' => __('admin.common.pay_manual'),
                            ])
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('payment_note')
                            ->label(fn() => __('admin.widget.pending.note_optional')),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markPaid($data['paid_via'], $data['payment_note'] ?? null);
                        Notification::make()->title(__('admin.invoice.mark_paid_done'))->success()->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->label(fn() => __('admin.common.view'))
                    ->url(fn($record) => PartnerInvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25]);
    }
}
