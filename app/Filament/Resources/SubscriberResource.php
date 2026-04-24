<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriberResource\Pages;
use App\Models\Agreement;
use App\Models\Subscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Partenaires';
    protected static ?string $navigationLabel = 'Clients (Subscribers)';
    protected static ?string $modelLabel = 'Client';
    protected static ?string $pluralModelLabel = 'Clients';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Partenaire')
                ->schema([
                    Forms\Components\Select::make('agreement_id')
                        ->label('Accord / Partenaire')
                        ->options(Agreement::pluck('partner_name', 'id'))
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $agreement = Agreement::find($state);
                            if ($agreement) {
                                $set('partner_firebase_id', $agreement->partner_firebase_id);
                            }
                        }),
                    Forms\Components\TextInput::make('partner_firebase_id')
                        ->label('Firebase ID partenaire')
                        ->required()
                        ->maxLength(128),
                ])->columns(2),

            Forms\Components\Section::make('Profil')
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
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Téléphone (E.164)')
                        ->helperText('Format: +33612345678')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('country')
                        ->label('Pays (ISO)')
                        ->maxLength(10),
                    Forms\Components\Select::make('language')
                        ->label('Langue')
                        ->options([
                            'fr' => 'Français',
                            'en' => 'English',
                            'es' => 'Español',
                            'de' => 'Deutsch',
                            'pt' => 'Português',
                            'ar' => 'العربية',
                            'zh' => '中文',
                            'ru' => 'Русский',
                            'hi' => 'हिन्दी',
                        ])
                        ->default('fr'),
                ])->columns(2),

            Forms\Components\Section::make('Hiérarchie (optionnel)')
                ->description('Pour les gros partenaires avec cabinets, régions ou départements. Tous les champs sont libres — le partenaire définit sa propre segmentation.')
                ->schema([
                    Forms\Components\TextInput::make('group_label')
                        ->label('Cabinet / Unité')
                        ->helperText('Ex: "Paris", "Lyon", "Direction", "Cabinet Nord"')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('region')
                        ->label('Région')
                        ->helperText('Ex: "Île-de-France", "APAC"')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('department')
                        ->label('Département / Service')
                        ->helperText('Ex: "IT", "RH", "Commercial"')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('external_id')
                        ->label('ID externe partenaire')
                        ->helperText('Identifiant dans le CRM du partenaire (optionnel)')
                        ->maxLength(255),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('SOS-Call')
                ->schema([
                    Forms\Components\TextInput::make('sos_call_code')
                        ->label('Code SOS-Call')
                        ->helperText('Format: PREFIX-YYYY-RANDOM5 (ex: XXX-2026-A3K9M)')
                        ->maxLength(20),
                    Forms\Components\DateTimePicker::make('sos_call_activated_at')
                        ->label('Activé le'),
                    Forms\Components\DateTimePicker::make('sos_call_expires_at')
                        ->label('Expire le (optionnel)'),
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'invited' => 'Invité',
                            'active' => 'Actif',
                            'suspended' => 'Suspendu',
                            'expired' => 'Expiré',
                        ])
                        ->default('active')
                        ->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('agreement.partner_name')
                    ->label('Partenaire')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sos_call_code')
                    ->label('Code SOS-Call')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Code copié')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('group_label')
                    ->label('Cabinet')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('region')
                    ->label('Région')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('department')
                    ->label('Dépt/Service')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID externe')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'invited',
                        'danger' => ['suspended', 'expired'],
                    ]),
                Tables\Columns\TextColumn::make('calls_expert')
                    ->label('Expert')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('calls_lawyer')
                    ->label('Avocat')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sos_call_expires_at')
                    ->label('Expire le')
                    ->date()
                    ->placeholder('Permanent')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'invited' => 'Invité',
                        'active' => 'Actif',
                        'suspended' => 'Suspendu',
                        'expired' => 'Expiré',
                    ]),
                Tables\Filters\SelectFilter::make('agreement_id')
                    ->label('Partenaire')
                    ->options(Agreement::pluck('partner_name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('has_sos_code')
                    ->label('Avec code SOS-Call')
                    ->query(fn($query) => $query->whereNotNull('sos_call_code')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'active')
                    ->action(fn($record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('reactivate')
                    ->label('Réactiver')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'suspended')
                    ->action(fn($record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspendre')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'suspended'])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscribers::route('/'),
            'create' => Pages\CreateSubscriber::route('/create'),
            'view' => Pages\ViewSubscriber::route('/{record}'),
            'edit' => Pages\EditSubscriber::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'active')->count();
    }
}
