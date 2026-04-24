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
    protected static ?string $navigationGroup = 'Gestion clients';
    protected static ?string $navigationLabel = 'Mes clients';
    protected static ?string $modelLabel = 'Client';
    protected static ?string $pluralModelLabel = 'Clients';
    protected static ?int $navigationSort = 1;

    /**
     * Global search (Cmd+K) — match on name/email/code/cabinet.
     * Scope is inherited from getEloquentQuery() (PartnerScopedQuery),
     * so cross-tenant leaks are impossible.
     */
    protected static ?string $recordTitleAttribute = 'email';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'sos_call_code', 'group_label'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Cabinet' => $record->group_label ?: '—',
            'Code' => $record->sos_call_code ?: '—',
            'Statut' => [
                'active' => 'Actif',
                'invited' => 'Invité',
                'suspended' => 'Suspendu',
            ][$record->status] ?? $record->status,
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
            Forms\Components\Section::make('Informations du client')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label('Prénom')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Nom')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Téléphone (format +33…)')
                        ->tel()
                        ->maxLength(30),
                    Forms\Components\Select::make('country')
                        ->label('Pays')
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
                        ->label('Langue')
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

            Forms\Components\Section::make('Hiérarchie (organisation interne)')
                ->description('Segmentation libre : cabinet, région, département. Utilisé pour les rapports drill-down.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('group_label')
                        ->label('Cabinet / unité')
                        ->placeholder('Ex : Paris, Lyon, Direction'),
                    Forms\Components\TextInput::make('region')
                        ->label('Région')
                        ->placeholder('Ex : Île-de-France'),
                    Forms\Components\TextInput::make('department')
                        ->label('Département / service')
                        ->placeholder('Ex : IT, RH, Commercial'),
                    Forms\Components\TextInput::make('external_id')
                        ->label('Référence interne')
                        ->placeholder('Ex : ID dans votre CRM'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Accès SOS-Call')
                ->collapsed()
                ->schema([
                    Forms\Components\DateTimePicker::make('sos_call_expires_at')
                        ->label('Expiration de l\'accès')
                        ->helperText('Laisser vide = aucune limite (accès valable tant que le contrat est actif)'),
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'active' => 'Actif',
                            'suspended' => 'Suspendu',
                            'invited' => 'Invité (pas encore activé)',
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
                    ->label('Prénom')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copié'),
                Tables\Columns\TextColumn::make('sos_call_code')
                    ->label('Code SOS-Call')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Code copié')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('group_label')
                    ->label('Cabinet')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('region')
                    ->label('Région')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('calls_total')
                    ->label('Appels')
                    ->state(fn($record) => ($record->calls_expert ?? 0) + ($record->calls_lawyer ?? 0))
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'invited',
                        'danger' => 'suspended',
                    ])
                    ->formatStateUsing(fn(string $state) => [
                        'active' => 'Actif',
                        'invited' => 'Invité',
                        'suspended' => 'Suspendu',
                    ][$state] ?? $state),
                Tables\Columns\TextColumn::make('sos_call_expires_at')
                    ->label('Expire le')
                    ->date()
                    ->placeholder('Sans limite')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Actif',
                        'invited' => 'Invité',
                        'suspended' => 'Suspendu',
                    ]),
                Tables\Filters\SelectFilter::make('group_label')
                    ->label('Cabinet')
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
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('reactivate')
                    ->label('Réactiver')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label('Exporter en CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $csv = "Prénom,Nom,Email,Téléphone,Code,Cabinet,Région,Département,Statut,Appels\n";
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
                            return response()->streamDownload(fn() => print($csv), 'mes-clients-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
                        }),

                    Tables\Actions\BulkAction::make('assignCabinet')
                        ->label('Assigner un cabinet')
                        ->icon('heroicon-o-building-office')
                        ->form([
                            Forms\Components\TextInput::make('group_label')
                                ->label('Cabinet / Unité')
                                ->required(),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['group_label' => $data['group_label']])),

                    Tables\Actions\BulkAction::make('assignRegion')
                        ->label('Assigner une région')
                        ->icon('heroicon-o-map')
                        ->form([
                            Forms\Components\TextInput::make('region')
                                ->label('Région')
                                ->required(),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['region' => $data['region']])),

                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspendre')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'suspended'])),

                    Tables\Actions\BulkAction::make('reactivate')
                        ->label('Réactiver')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'active'])),

                    Tables\Actions\BulkAction::make('extendExpiration')
                        ->label('Prolonger l\'accès')
                        ->icon('heroicon-o-clock')
                        ->form([
                            Forms\Components\Select::make('days')
                                ->label('Durée supplémentaire')
                                ->options([
                                    '30' => '+ 30 jours',
                                    '90' => '+ 90 jours',
                                    '180' => '+ 180 jours',
                                    '365' => '+ 1 an',
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

    /**
     * Enforce partner_firebase_id on create — the user cannot choose whose
     * partner this subscriber belongs to; it's always their own.
     */
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
