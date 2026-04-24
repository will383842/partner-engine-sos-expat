<?php

namespace App\Filament\Widgets;

use App\Models\SubscriberActivity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent 20 SOS-Call calls across all partners.
 * Gives admin visibility on live activity.
 */
class RecentCallsWidget extends BaseWidget
{
    protected static ?string $heading = '20 derniers appels SOS-Call';
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return SubscriberActivity::query()
            ->where('type', 'call_completed')
            ->orderBy('created_at', 'desc')
            ->limit(20);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Quand')
                    ->since()
                    ->tooltip(fn($record) => $record->created_at?->format('Y-m-d H:i:s')),
                Tables\Columns\TextColumn::make('subscriber.agreement.partner_name')
                    ->label('Partenaire')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subscriber.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn($state, $record) => trim(($record->subscriber->first_name ?? '') . ' ' . ($record->subscriber->last_name ?? ''))),
                Tables\Columns\TextColumn::make('subscriber.sos_call_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('provider_type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'lawyer',
                        'info' => 'expat',
                    ])
                    ->formatStateUsing(fn($state) => $state === 'lawyer' ? '⚖️ Avocat' : '👤 Expert'),
                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Durée')
                    ->formatStateUsing(fn($state) => $state ? round($state / 60) . ' min' : '—'),
            ])
            ->paginated(false);
    }
}
