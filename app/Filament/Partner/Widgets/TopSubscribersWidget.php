<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Subscriber;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Top 10 subscribers by call volume this month for the partner dashboard.
 *
 * We query Subscriber (so every row carries its native primary key, which
 * Filament's TableWidget requires) and eager-append the monthly call
 * count as `calls_count` via withCount().
 */
class TopSubscribersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 10 clients les plus actifs (ce mois)';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return Subscriber::query()
            ->when(!$partnerId, fn($q) => $q->whereRaw('1 = 0'))
            ->where('partner_firebase_id', $partnerId)
            ->whereHas('activities', function ($q) use ($monthStart, $monthEnd) {
                $q->where('type', 'call_completed')
                  ->whereBetween('created_at', [$monthStart, $monthEnd]);
            })
            ->withCount([
                'activities as calls_count' => function ($q) use ($monthStart, $monthEnd) {
                    $q->where('type', 'call_completed')
                      ->whereBetween('created_at', [$monthStart, $monthEnd]);
                },
            ])
            ->orderByDesc('calls_count')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Client')
                    ->state(fn($record) => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: ($record->email ?? 'Client #' . $record->id)),
                Tables\Columns\TextColumn::make('group_label')
                    ->label('Cabinet')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sos_call_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('calls_count')
                    ->label('Appels ce mois')
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Aucun appel enregistré ce mois');
    }
}
