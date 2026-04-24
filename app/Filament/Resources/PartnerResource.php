<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Agreement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerResource extends Resource
{
    protected static ?string $model = Agreement::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Partenaires';
    protected static ?string $navigationLabel = 'Partenaires';
    protected static ?string $modelLabel = 'Partenaire';
    protected static ?string $pluralModelLabel = 'Partenaires';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Informations générales')
                    ->schema([
                        Forms\Components\TextInput::make('partner_name')
                            ->label('Nom du partenaire')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('partner_firebase_id')
                            ->label('Firebase ID')
                            ->required()
                            ->maxLength(128)
                            ->helperText('Identifiant unique utilisé par les intégrations Firebase'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nom interne de l\'accord')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('billing_email')
                            ->label('Email facturation')
                            ->email()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Wizard\Step::make('Statut et dates')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options([
                                'active' => 'Actif',
                                'paused' => 'Suspendu',
                                'expired' => 'Expiré',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Date de début')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Date d\'expiration (optionnel)'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes internes')
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Wizard\Step::make('Modèle économique')
                    ->description('Choisissez UN modèle — les champs inutiles sont automatiquement remis à zéro.')
                    ->schema([
                        Forms\Components\Radio::make('economic_model')
                            ->label('Modèle économique appliqué à ce partenaire')
                            ->options([
                                'commission' => '💱 Commission à l\'acte — partenaire touche X par appel, client paye le prix standard (19€/49€)',
                                'sos_call' => '💰 SOS-Call forfait mensuel — partenaire paye X€/client/mois, client appelle gratuitement',
                                'hybrid' => '⚠️ Hybride (rare) — forfait mensuel + commission par appel en plus',
                            ])
                            ->default('commission')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                // Quand on passe en sos_call pur, on remet les commissions à 0
                                // et on active sos_call_active.
                                if ($state === 'sos_call') {
                                    $set('commission_per_call_lawyer', 0);
                                    $set('commission_per_call_expat', 0);
                                    $set('commission_percent', 0);
                                    $set('sos_call_active', true);
                                    return;
                                }
                                // En commission pure, on désactive sos_call.
                                if ($state === 'commission') {
                                    $set('sos_call_active', false);
                                    return;
                                }
                                // Hybride: on active sos_call, on garde les commissions
                                // (l'admin renseignera les valeurs lui-même)
                                if ($state === 'hybrid') {
                                    $set('sos_call_active', true);
                                }
                            })
                            ->columnSpanFull(),

                        // Field caché techniquement utilisé par le backend, synchro avec le radio
                        Forms\Components\Hidden::make('sos_call_active'),

                        // ========== BLOC COMMISSION (commission | hybrid) ==========
                        Forms\Components\Section::make('Paramètres commission à l\'acte')
                            ->description('S\'applique sur chaque appel payé par le client final')
                            ->icon('heroicon-o-banknotes')
                            ->visible(fn(Forms\Get $get) => in_array($get('economic_model'), ['commission', 'hybrid'], true))
                            ->schema([
                                Forms\Components\TextInput::make('commission_per_call_lawyer')
                                    ->label('Commission avocat (cents)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('En centimes — ex: 300 = 3€'),
                                Forms\Components\TextInput::make('commission_per_call_expat')
                                    ->label('Commission expert (cents)')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Select::make('commission_type')
                                    ->label('Type de commission')
                                    ->options([
                                        'fixed' => 'Fixe par appel',
                                        'percent' => 'Pourcentage',
                                    ])
                                    ->default('fixed'),
                                Forms\Components\TextInput::make('commission_percent')
                                    ->label('Commission %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ])
                            ->columns(2),

                        // ========== BLOC SOS-CALL FORFAIT (sos_call | hybrid) ==========
                        Forms\Components\Section::make('Paramètres SOS-Call forfait mensuel')
                            ->description('Le partenaire est facturé chaque mois. Ses clients appellent gratuitement via sos-call.sos-expat.com')
                            ->icon('heroicon-o-phone')
                            ->visible(fn(Forms\Get $get) => in_array($get('economic_model'), ['sos_call', 'hybrid'], true))
                            ->schema([
                                Forms\Components\TextInput::make('billing_rate')
                                    ->label('Tarif mensuel par client actif')
                                    ->numeric()
                                    ->default(3.00)
                                    ->step(0.01)
                                    ->required(),
                                Forms\Components\Select::make('billing_currency')
                                    ->label('Devise de facturation')
                                    ->options([
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ])
                                    ->default('EUR')
                                    ->required(),
                                Forms\Components\TextInput::make('payment_terms_days')
                                    ->label('Délai de paiement (jours)')
                                    ->numeric()
                                    ->default(15)
                                    ->minValue(0)
                                    ->maxValue(90),
                                Forms\Components\Select::make('call_types_allowed')
                                    ->label('Types d\'appels autorisés')
                                    ->options([
                                        'both' => 'Expert + Avocat',
                                        'expat_only' => 'Expert seulement',
                                        'lawyer_only' => 'Avocat seulement',
                                    ])
                                    ->default('both'),
                            ])
                            ->columns(2),
                    ]),

                Forms\Components\Wizard\Step::make('Limites et quotas')
                    ->schema([
                        Forms\Components\TextInput::make('max_subscribers')
                            ->label('Max subscribers (0 = illimité)')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('max_calls_per_subscriber')
                            ->label('Max appels / subscriber (0 = illimité)')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('default_subscriber_duration_days')
                            ->label('Durée par défaut subscriber (jours)')
                            ->numeric()
                            ->helperText('Nombre de jours avant expiration — laissez vide pour durée illimitée'),
                        Forms\Components\TextInput::make('max_subscriber_duration_days')
                            ->label('Durée max subscriber (jours)')
                            ->numeric(),
                    ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partner_name')
                    ->label('Partenaire')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label('Firebase ID')
                    ->searchable()
                    ->limit(15)
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'expired',
                    ]),
                Tables\Columns\BadgeColumn::make('economic_model')
                    ->label('Modèle')
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'commission' => 'Commission',
                        'sos_call' => 'SOS-Call',
                        'hybrid' => 'Hybride',
                        default => '—',
                    })
                    ->colors([
                        'warning' => 'commission',
                        'success' => 'sos_call',
                        'danger' => 'hybrid',
                    ]),
                Tables\Columns\TextColumn::make('billing_rate')
                    ->label('Tarif/mois')
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->label('Clients')
                    ->counts('subscribers')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('billing_email')
                    ->label('Email fact.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->date()
                    ->sortable()
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
                        'active' => 'Actif',
                        'paused' => 'Suspendu',
                        'expired' => 'Expiré',
                    ]),
                Tables\Filters\SelectFilter::make('economic_model')
                    ->label('Modèle économique')
                    ->options([
                        'commission' => 'Commission à l\'acte',
                        'sos_call' => 'SOS-Call forfait',
                        'hybrid' => 'Hybride',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleSosCall')
                    ->label(fn($record) => $record->economic_model === 'commission' ? 'Basculer en SOS-Call' : 'Basculer en Commission')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription(fn($record) => $record->economic_model === 'commission'
                        ? "Ce partenaire passera du modèle Commission (paiement à l'acte) au modèle SOS-Call (forfait mensuel). Les tarifs commission seront remis à 0."
                        : "Ce partenaire repassera en modèle Commission. Le forfait mensuel SOS-Call sera désactivé.")
                    ->action(function ($record) {
                        if ($record->economic_model === 'commission') {
                            $record->update([
                                'economic_model' => 'sos_call',
                                'sos_call_active' => true,
                                'commission_per_call_lawyer' => 0,
                                'commission_per_call_expat' => 0,
                                'commission_percent' => 0,
                                // Defaults if first activation
                                'billing_rate' => $record->billing_rate ?: 3.00,
                                'billing_currency' => $record->billing_currency ?: 'EUR',
                                'payment_terms_days' => $record->payment_terms_days ?: 15,
                                'call_types_allowed' => $record->call_types_allowed ?: 'both',
                            ]);
                        } else {
                            $record->update([
                                'economic_model' => 'commission',
                                'sos_call_active' => false,
                            ]);
                        }
                    }),

                // 🚨 Suspension manuelle des subscribers (sécurisée, pas automatique)
                Tables\Actions\Action::make('suspendAllSubscribers')
                    ->label('Suspendre tous les clients')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Suspendre tous les clients de ce partenaire ?')
                    ->modalDescription(fn($record) => "ATTENTION : cette action va bloquer l'accès SOS-Call pour tous les clients actifs de « {$record->partner_name} ». À utiliser uniquement après plusieurs relances sans paiement. Pour les gros comptes B2B, privilégier un contact commercial direct d'abord.")
                    ->modalSubmitActionLabel('Confirmer la suspension')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Raison de la suspension (log audit)')
                            ->required()
                            ->placeholder('Ex: facture SOS-202604-0001 impayée depuis 45 jours, 3 relances sans réponse'),
                    ])
                    ->action(function ($record, array $data) {
                        $count = \App\Models\Subscriber::where('agreement_id', $record->id)
                            ->where('status', 'active')
                            ->update(['status' => 'suspended']);

                        \App\Models\AuditLog::create([
                            'actor_firebase_id' => 'admin:filament',
                            'actor_role' => 'admin',
                            'action' => 'partner_subscribers_suspended',
                            'resource_type' => 'agreement',
                            'resource_id' => (string) $record->id,
                            'details' => [
                                'partner_name' => $record->partner_name,
                                'suspended_count' => $count,
                                'reason' => $data['reason'],
                            ],
                            'created_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title("{$count} clients suspendus")
                            ->body("Partenaire: {$record->partner_name}")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('reactivateAllSubscribers')
                    ->label('Réactiver tous les clients')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Réactiver tous les clients de ce partenaire ?')
                    ->modalDescription('Rend à nouveau opérationnel l\'accès SOS-Call pour tous les clients suspendus de ce partenaire.')
                    ->action(function ($record) {
                        $count = \App\Models\Subscriber::where('agreement_id', $record->id)
                            ->where('status', 'suspended')
                            ->update(['status' => 'active']);

                        \App\Models\AuditLog::create([
                            'actor_firebase_id' => 'admin:filament',
                            'actor_role' => 'admin',
                            'action' => 'partner_subscribers_reactivated',
                            'resource_type' => 'agreement',
                            'resource_id' => (string) $record->id,
                            'details' => [
                                'partner_name' => $record->partner_name,
                                'reactivated_count' => $count,
                            ],
                            'created_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title("{$count} clients réactivés")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            \Illuminate\Database\Eloquent\SoftDeletingScope::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'view' => Pages\ViewPartner::route('/{record}'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'active')->count();
    }
}
