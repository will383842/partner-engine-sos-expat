<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\PartnerScopedQuery;
use App\Filament\Partner\Resources\ActivityResource\Pages;
use App\Models\SubscriberActivity;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityResource extends Resource
{
    use PartnerScopedQuery;

    protected static ?string $model = SubscriberActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_pilotage');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.activity');
    }

    public static function getModelLabel(): string
    {
        return __('panel.activity.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.activity.plural_label');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query
                ->where('type', 'call_completed')
                // Eager-load subscriber to avoid N+1 (rows access subscriber.first_name,
                // subscriber.last_name, subscriber.group_label, subscriber.sos_call_code,
                // subscriber.email — without this each row fired 5 extra SELECTs).
                ->with('subscriber:id,first_name,last_name,email,group_label,sos_call_code')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('panel.activity.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriber.first_name')
                    ->label(fn() => __('panel.activity.client'))
                    ->formatStateUsing(fn($state, $record) => trim(($record->subscriber->first_name ?? '') . ' ' . ($record->subscriber->last_name ?? '')) ?: __('panel.common.dash'))
                    ->searchable(['subscribers.first_name', 'subscribers.last_name', 'subscribers.email']),
                Tables\Columns\TextColumn::make('subscriber.group_label')
                    ->label(fn() => __('panel.activity.cabinet'))
                    ->placeholder(fn() => __('panel.common.dash'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscriber.sos_call_code')
                    ->label(fn() => __('panel.activity.code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('panel.common.copy_code'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('provider_type')
                    ->label(fn() => __('panel.activity.type'))
                    ->colors([
                        'danger' => 'lawyer',
                        'info' => 'expat',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'lawyer' => __('panel.common.lawyer'),
                        'expat'  => __('panel.common.expert'),
                        default  => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('call_duration_seconds')
                    ->label(fn() => __('panel.activity.duration'))
                    ->formatStateUsing(fn($state) => $state
                        ? __('panel.activity.duration_minutes', ['m' => round($state / 60)])
                        : __('panel.common.dash')),
                Tables\Columns\TextColumn::make('metadata.country')
                    ->label(fn() => __('panel.activity.country'))
                    ->placeholder(fn() => __('panel.common.dash'))
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('provider_type')
                    ->label(fn() => __('panel.activity.filter_type'))
                    ->options([
                        'lawyer' => __('panel.common.lawyer'),
                        'expat'  => __('panel.common.expert'),
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->label(fn() => __('panel.activity.filter_period'))
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label(fn() => __('panel.activity.filter_from')),
                        \Filament\Forms\Components\DatePicker::make('to')->label(fn() => __('panel.activity.filter_to')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(fn() => __('panel.activity.action_detail')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label(fn() => __('panel.common.export_csv'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // BOM UTF-8 so Excel on Windows shows accents correctly.
                            $csv = "\xEF\xBB\xBF"
                                . __('panel.activity.date') . ',' . __('panel.activity.client') . ','
                                . __('panel.subscriber.email') . ',' . __('panel.activity.cabinet') . ','
                                . __('panel.activity.code') . ',' . __('panel.activity.type') . ','
                                . __('panel.activity.duration') . ',' . __('panel.activity.country') . "\n";
                            foreach ($records as $r) {
                                $s = $r->subscriber;
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,\"%s\"\n",
                                    $r->created_at?->format('Y-m-d H:i') ?? '',
                                    str_replace('"', '""', trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''))),
                                    str_replace('"', '""', $s->email ?? ''),
                                    str_replace('"', '""', $s->group_label ?? ''),
                                    str_replace('"', '""', $s->sos_call_code ?? ''),
                                    $r->provider_type === 'lawyer' ? __('panel.common.lawyer') : __('panel.common.expert'),
                                    $r->call_duration_seconds ? round($r->call_duration_seconds / 60) : 0,
                                    str_replace('"', '""', $r->metadata['country'] ?? '')
                                );
                            }
                            return response()->streamDownload(fn() => print($csv), 'activity-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
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
