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
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widget.overdue.heading');
    }

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
                    ->label(fn() => __('admin.invoice.invoice_number_short'))
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label(fn() => __('admin.invoice.partner'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label(fn() => __('admin.invoice.period_short')),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(fn() => __('admin.invoice.amount'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(fn() => __('admin.invoice.due_date_short'))
                    ->date()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label(fn() => __('admin.widget.overdue.col_delay'))
                    ->state(fn($record) => __('admin.widget.overdue.days_overdue', ['days' => now()->diffInDays($record->due_date)]))
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(fn() => __('admin.common.view'))
                    ->url(fn($record) => PartnerInvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
