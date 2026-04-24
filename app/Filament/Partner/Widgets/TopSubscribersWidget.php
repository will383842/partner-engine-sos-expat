<?php

namespace App\Filament\Partner\Widgets;

use App\Models\SubscriberActivity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Top 10 subscribers by call volume this month, for the partner dashboard.
 */
class TopSubscribersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 10 clients les plus actifs (ce mois)';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;

        return SubscriberActivity::query()
            ->when(!$partnerId, fn($q) => $q->whereRaw('1 = 0'))
            ->where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->select('subscriber_id', DB::raw('COUNT(*) as calls_count'))
            ->groupBy('subscriber_id')
            ->orderByDesc('calls_count')
            ->limit(10)
            ->with(['subscriber']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('subscriber.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn($state, $record) => trim(($record->subscriber->first_name ?? '') . ' ' . ($record->subscriber->last_name ?? '')) ?: 'Client #' . $record->subscriber_id),
                Tables\Columns\TextColumn::make('subscriber.group_label')
                    ->label('Cabinet')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subscriber.sos_call_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('calls_count')
                    ->label('Appels ce mois')
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }
}
