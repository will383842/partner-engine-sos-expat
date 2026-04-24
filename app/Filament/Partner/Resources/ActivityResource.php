<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\PartnerScopedQuery;
use App\Filament\Partner\Resources\ActivityResource\Pages;
use App\Models\SubscriberActivity;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only timeline of SOS-Call activity (calls placed) by the
 * partner's subscribers. Scoped to the logged-in partner.
 */
class ActivityResource extends Resource
{
    use PartnerScopedQuery;

    protected static ?string $model = SubscriberActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationGroup = 'Pilotage';
    protected static ?string $navigationLabel = 'Activité SOS-Call';
    protected static ?string $modelLabel = 'Appel';
    protected static ?string $pluralModelLabel = 'Appels';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->where('type', 'call_completed'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriber.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn($state, $record) => trim(($record->subscriber->first_name ?? '') . ' ' . ($record->subscriber->last_name ?? '')) ?: '—')
                    ->searchable(['subscribers.first_name', 'subscribers.last_name', 'subscribers.email']),
                Tables\Columns\TextColumn::make('subscriber.group_label')
                    ->label('Cabinet')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscriber.sos_call_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('provider_type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'lawyer',
                        'info' => 'expat',
                    ])
                    ->formatStateUsing(fn($state) => $state === 'lawyer' ? 'Avocat' : ($state === 'expat' ? 'Expert' : $state)),
                Tables\Columns\TextColumn::make('call_duration_seconds')
                    ->label('Durée')
                    ->formatStateUsing(fn($state) => $state ? round($state / 60) . ' min' : '—'),
                Tables\Columns\TextColumn::make('metadata.country')
                    ->label('Pays')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('provider_type')
                    ->label('Type')
                    ->options([
                        'lawyer' => 'Avocat',
                        'expat' => 'Expert',
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->label('Période')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Du'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('Au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Détail'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label('Exporter en CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $csv = "Date,Client,Email,Cabinet,Code,Type,Durée (min),Pays\n";
                            foreach ($records as $r) {
                                $s = $r->subscriber;
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,\"%s\"\n",
                                    $r->created_at?->format('Y-m-d H:i') ?? '',
                                    str_replace('"', '""', trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''))),
                                    str_replace('"', '""', $s->email ?? ''),
                                    str_replace('"', '""', $s->group_label ?? ''),
                                    str_replace('"', '""', $s->sos_call_code ?? ''),
                                    $r->provider_type === 'lawyer' ? 'Avocat' : 'Expert',
                                    $r->call_duration_seconds ? round($r->call_duration_seconds / 60) : 0,
                                    str_replace('"', '""', $r->metadata['country'] ?? '')
                                );
                            }
                            return response()->streamDownload(fn() => print($csv), 'activite-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivity::route('/'),
        ];
    }
}
