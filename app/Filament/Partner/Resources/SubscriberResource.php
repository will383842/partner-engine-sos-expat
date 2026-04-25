<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Concerns\PartnerScopedQuery;
use App\Filament\Partner\Resources\SubscriberResource\Pages;
use App\Models\Subscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriberResource extends Resource
{
    use PartnerScopedQuery;

    protected static ?string $model = Subscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;

    // Localized labels — static ::$foo cannot be a closure so we override
    // the getter methods Filament looks up on every render.
    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_clients');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.subscribers');
    }

    public static function getModelLabel(): string
    {
        return __('panel.subscriber.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.subscriber.plural_label');
    }

    protected static ?string $recordTitleAttribute = 'email';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'sos_call_code', 'group_label'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            __('panel.subscriber.group_label') => $record->group_label ?: __('panel.common.dash'),
            __('panel.subscriber.sos_code')    => $record->sos_call_code ?: __('panel.common.dash'),
            __('panel.common.status')          => __('panel.common.' . ($record->status ?? 'active')),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user?->partner_firebase_id) return null;
        return (string) Subscriber::where('partner_firebase_id', $user->partner_firebase_id)
            ->where('status', 'active')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn() => __('panel.subscriber.section_info'))
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label(fn() => __('panel.subscriber.first_name'))
                        ->maxLength(100),
                    Forms\Components\TextInput::make('last_name')
                        ->label(fn() => __('panel.subscriber.last_name'))
                        ->maxLength(100),
                    Forms\Components\TextInput::make('email')
                        ->label(fn() => __('panel.subscriber.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(fn() => __('panel.subscriber.phone'))
                        ->helperText(fn() => __('panel.subscriber.phone_hint'))
                        ->tel()
                        ->maxLength(30),
                    Forms\Components\Select::make('country')
                        ->label(fn() => __('panel.subscriber.country'))
                        ->options([
                            'FR' => 'France',
                            'ES' => 'Espagne',
                            'PT' => 'Portugal',
                            'DE' => 'Allemagne',
                            'IT' => 'Italie',
                            'GB' => 'Royaume-Uni',
                            'US' => 'États-Unis',
                            'CA' => 'Canada',
                            'MA' => 'Maroc',
                            'TN' => 'Tunisie',
                            'DZ' => 'Algérie',
                            'TH' => 'Thaïlande',
                            'VN' => 'Vietnam',
                        ])
                        ->searchable(),
                    Forms\Components\Select::make('language')
                        ->label(fn() => __('panel.subscriber.language'))
                        ->options([
                            'fr' => 'Français',
                            'en' => 'English',
                            'es' => 'Español',
                            'de' => 'Deutsch',
                            'pt' => 'Português',
                        ])
                        ->default('fr'),
                ])
                ->columns(2),

            Forms\Components\Section::make(fn() => __('panel.subscriber.section_hierarchy'))
                ->description(fn() => __('panel.subscriber.section_hierarchy_desc'))
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('group_label')
                        ->label(fn() => __('panel.subscriber.group_label'))
                        ->placeholder(fn() => __('panel.subscriber.group_label_placeholder')),
                    Forms\Components\TextInput::make('region')
                        ->label(fn() => __('panel.subscriber.region'))
                        ->placeholder(fn() => __('panel.subscriber.region_placeholder')),
                    Forms\Components\TextInput::make('department')
                        ->label(fn() => __('panel.subscriber.department'))
                        ->placeholder(fn() => __('panel.subscriber.department_placeholder')),
                    Forms\Components\TextInput::make('external_id')
                        ->label(fn() => __('panel.subscriber.external_id'))
                        ->placeholder(fn() => __('panel.subscriber.external_id_placeholder')),
                ])
                ->columns(2),

            Forms\Components\Section::make(fn() => __('panel.subscriber.section_access'))
                ->collapsed()
                ->schema([
                    Forms\Components\DateTimePicker::make('sos_call_expires_at')
                        ->label(fn() => __('panel.subscriber.expires_at'))
                        ->helperText(fn() => __('panel.subscriber.expires_hint')),
                    Forms\Components\Select::make('status')
                        ->label(fn() => __('panel.common.status'))
                        ->options([
                            'active' => __('panel.common.active'),
                            'suspended' => __('panel.common.suspended'),
                            'invited' => __('panel.common.invited'),
                        ])
                        ->default('active'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label(fn() => __('panel.subscriber.first_name'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label(fn() => __('panel.subscriber.last_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(fn() => __('panel.subscriber.email'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage(fn() => __('panel.common.copy_email')),
                Tables\Columns\TextColumn::make('sos_call_code')
                    ->label(fn() => __('panel.subscriber.sos_code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('panel.common.copy_code'))
                    ->placeholder(fn() => __('panel.common.dash')),
                Tables\Columns\TextColumn::make('group_label')
                    ->label(fn() => __('panel.subscriber.group_label'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('region')
                    ->label(fn() => __('panel.subscriber.region'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('calls_total')
                    ->label(fn() => __('panel.subscriber.calls_total'))
                    ->state(fn($record) => ($record->calls_expert ?? 0) + ($record->calls_lawyer ?? 0))
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(fn() => __('panel.common.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'invited',
                        'danger' => 'suspended',
                    ])
                    ->formatStateUsing(fn(string $state) => __('panel.common.' . $state)),
                Tables\Columns\TextColumn::make('sos_call_expires_at')
                    ->label(fn() => __('panel.subscriber.expires_at'))
                    ->date()
                    ->placeholder(fn() => __('panel.common.no_limit'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('panel.subscriber.added_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(fn() => __('panel.common.status'))
                    ->options([
                        'active' => __('panel.common.active'),
                        'invited' => __('panel.common.invited'),
                        'suspended' => __('panel.common.suspended'),
                    ]),
                Tables\Filters\SelectFilter::make('group_label')
                    ->label(fn() => __('panel.subscriber.filter_cabinet'))
                    ->options(function () {
                        $user = auth()->user();
                        return Subscriber::where('partner_firebase_id', $user?->partner_firebase_id)
                            ->whereNotNull('group_label')
                            ->distinct()
                            ->orderBy('group_label')
                            ->pluck('group_label', 'group_label')
                            ->toArray();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label(fn() => __('panel.subscriber.action_suspend'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('reactivate')
                    ->label(fn() => __('panel.subscriber.action_reactivate'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label(fn() => __('panel.common.export_csv'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // BOM UTF-8 so Excel on Windows shows accents correctly.
                            $csv = "\xEF\xBB\xBF"
                                . __('panel.subscriber.first_name') . ',' . __('panel.subscriber.last_name') . ','
                                . __('panel.subscriber.email') . ',' . __('panel.subscriber.phone') . ','
                                . __('panel.subscriber.sos_code') . ',' . __('panel.subscriber.group_label') . ','
                                . __('panel.subscriber.region') . ',' . __('panel.subscriber.department') . ','
                                . __('panel.common.status') . ',' . __('panel.subscriber.calls_total') . "\n";
                            foreach ($records as $r) {
                                $total = ($r->calls_expert ?? 0) + ($r->calls_lawyer ?? 0);
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d\n",
                                    str_replace('"', '""', $r->first_name ?? ''),
                                    str_replace('"', '""', $r->last_name ?? ''),
                                    str_replace('"', '""', $r->email ?? ''),
                                    str_replace('"', '""', $r->phone ?? ''),
                                    str_replace('"', '""', $r->sos_call_code ?? ''),
                                    str_replace('"', '""', $r->group_label ?? ''),
                                    str_replace('"', '""', $r->region ?? ''),
                                    str_replace('"', '""', $r->department ?? ''),
                                    str_replace('"', '""', $r->status ?? ''),
                                    $total
                                );
                            }
                            return response()->streamDownload(fn() => print($csv), 'clients-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
                        }),

                    Tables\Actions\BulkAction::make('assignCabinet')
                        ->label(fn() => __('panel.subscriber.action_assign_cabinet'))
                        ->icon('heroicon-o-building-office')
                        ->form([
                            Forms\Components\TextInput::make('group_label')
                                ->label(fn() => __('panel.subscriber.group_label'))
                                ->required(),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['group_label' => $data['group_label']])),

                    Tables\Actions\BulkAction::make('assignRegion')
                        ->label(fn() => __('panel.subscriber.action_assign_region'))
                        ->icon('heroicon-o-map')
                        ->form([
                            Forms\Components\TextInput::make('region')
                                ->label(fn() => __('panel.subscriber.region'))
                                ->required(),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['region' => $data['region']])),

                    Tables\Actions\BulkAction::make('suspend')
                        ->label(fn() => __('panel.subscriber.action_suspend'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'suspended'])),

                    Tables\Actions\BulkAction::make('reactivate')
                        ->label(fn() => __('panel.subscriber.action_reactivate'))
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'active'])),

                    Tables\Actions\BulkAction::make('extendExpiration')
                        ->label(fn() => __('panel.subscriber.action_extend'))
                        ->icon('heroicon-o-clock')
                        ->form([
                            Forms\Components\Select::make('days')
                                ->label(fn() => __('panel.subscriber.extend_duration'))
                                ->options([
                                    '30'  => __('panel.subscriber.extend_30d'),
                                    '90'  => __('panel.subscriber.extend_90d'),
                                    '180' => __('panel.subscriber.extend_180d'),
                                    '365' => __('panel.subscriber.extend_365d'),
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $days = (int) $data['days'];
                            foreach ($records as $r) {
                                $base = $r->sos_call_expires_at ?? now();
                                $r->update(['sos_call_expires_at' => $base->copy()->addDays($days)]);
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['partner_firebase_id'] = $user?->partner_firebase_id;
        if ($user?->agreement) {
            $data['agreement_id'] = $user->agreement->id;
        }
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscribers::route('/'),
            'create' => Pages\CreateSubscriber::route('/create'),
            'view'   => Pages\ViewSubscriber::route('/{record}'),
            'edit'   => Pages\EditSubscriber::route('/{record}/edit'),
        ];
    }
}
