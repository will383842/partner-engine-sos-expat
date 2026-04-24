<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerApiKeyResource\Pages;
use App\Models\Agreement;
use App\Models\PartnerApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerApiKeyResource extends Resource
{
    protected static ?string $model = PartnerApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Configuration';
    protected static ?string $navigationLabel = 'Clés API partenaires';
    protected static ?string $modelLabel = 'Clé API';
    protected static ?string $pluralModelLabel = 'Clés API';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('partner_firebase_id')
                ->label('Partenaire')
                ->options(Agreement::pluck('partner_name', 'partner_firebase_id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Libellé (description interne)')
                ->placeholder('ex: Production CRM integration')
                ->required()
                ->maxLength(100),
            Forms\Components\CheckboxList::make('scopes_array')
                ->label('Scopes autorisés')
                ->options([
                    'subscribers:read' => 'Lire les clients',
                    'subscribers:write' => 'Créer / modifier / supprimer des clients',
                    'activity:read' => 'Lire l\'activité',
                    'invoices:read' => 'Lire les factures',
                ])
                ->default(['subscribers:write', 'subscribers:read', 'activity:read'])
                ->required()
                ->helperText('Permissions accordées à cette clé. Principe du moindre privilège.'),
            Forms\Components\Radio::make('environment')
                ->label('Environnement')
                ->options(['live' => 'Production (pk_live_…)', 'test' => 'Sandbox (pk_test_…)'])
                ->default('live')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prefix')
                    ->label('Clé (préfixe)')
                    ->fontFamily('mono')
                    ->description(fn($r) => $r->name),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label('Partenaire')
                    ->formatStateUsing(fn($state) => Agreement::where('partner_firebase_id', $state)->value('partner_name') ?: $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Dernière utilisation')
                    ->since()
                    ->placeholder('Jamais'),
                Tables\Columns\IconColumn::make('revoked_at')
                    ->label('Révoquée')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label('Révoquée')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Cette clé cessera immédiatement de fonctionner. Cette action est irréversible.')
                    ->visible(fn($record) => !$record->isRevoked())
                    ->action(function ($record) {
                        $record->revoke('admin:filament');
                        Notification::make()->title('Clé révoquée')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerApiKeys::route('/'),
            'create' => Pages\CreatePartnerApiKey::route('/create'),
        ];
    }
}
