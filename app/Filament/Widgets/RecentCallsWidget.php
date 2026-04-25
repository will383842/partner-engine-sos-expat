<?php

namespace App\Filament\Widgets;

use App\Models\SubscriberActivity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentCallsWidget extends BaseWidget
{
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widget.recent_calls.heading');
    }

    protected function getTableQuery(): Builder
    {
        return SubscriberActivity::query()
            ->where('type', 'call_completed')
            ->with(['subscriber' => fn($q) => $q->with('agreement:id,partner_name')])
            ->orderBy('created_at', 'desc')
            ->limit(20);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.widget.recent_calls.col_when'))
                    ->since()
                    ->tooltip(fn($record) => $record->created_at?->format('Y-m-d H:i:s')),
                Tables\Columns\TextColumn::make('subscriber.agreement.partner_name')
                    ->label(fn() => __('admin.widget.recent_calls.col_partner'))
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\TextColumn::make('subscriber.first_name')
                    ->label(fn() => __('admin.widget.recent_calls.col_client'))
                    ->formatStateUsing(fn($state, $record) => trim(($record->subscriber->first_name ?? '') . ' ' . ($record->subscriber->last_name ?? ''))),
                Tables\Columns\TextColumn::make('subscriber.sos_call_code')
                    ->label(fn() => __('admin.widget.recent_calls.col_code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('admin.common.copy_code'))
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\BadgeColumn::make('provider_type')
                    ->label(fn() => __('admin.widget.recent_calls.col_type'))
                    ->colors([
                        'danger' => 'lawyer',
                        'info' => 'expat',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'lawyer' => __('admin.widget.recent_calls.lawyer_emoji'),
                        'expat' => __('admin.widget.recent_calls.expert_emoji'),
                        default => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('call_duration_seconds')
                    ->label(fn() => __('admin.widget.recent_calls.col_duration'))
                    ->formatStateUsing(fn($state) => $state
                        ? __('admin.widget.recent_calls.minutes', ['m' => round($state / 60)])
                        : __('admin.common.dash')),
            ])
            ->paginated(false);
    }
}
