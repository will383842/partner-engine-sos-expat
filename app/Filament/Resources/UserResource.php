<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Configuration';
    protected static ?string $navigationLabel = 'Utilisateurs admin';
    protected static ?string $modelLabel = 'Utilisateur';
    protected static ?string $pluralModelLabel = 'Utilisateurs';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identité')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom complet')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Accès')
                ->schema([
                    Forms\Components\Select::make('role')
                        ->label('Rôle')
                        ->options([
                            'super_admin' => 'Super Admin (tout + impersonate + delete)',
                            'admin' => 'Admin (CRUD complet)',
                            'accountant' => 'Comptable (factures + rapports)',
                            'support' => 'Support (read + édit. limitée)',
                        ])
                        ->required()
                        ->default('admin'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Compte actif')
                        ->default(true),
                    Forms\Components\TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->dehydrateStateUsing(fn($state) => !empty($state) ? Hash::make($state) : null)
                        ->dehydrated(fn($state) => !empty($state))
                        ->required(fn(string $context) => $context === 'create')
                        ->minLength(12)
                        ->helperText('Minimum 12 caractères. Laisser vide pour conserver le mot de passe actuel.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rôle')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'info' => 'accountant',
                        'gray' => 'support',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Dernière connexion')
                    ->dateTime()
                    ->placeholder('Jamais')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'accountant' => 'Comptable',
                        'support' => 'Support',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Compte actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn($record) => $record->is_active ? 'Désactiver' : 'Activer')
                    ->icon('heroicon-o-power')
                    ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['is_active' => !$record->is_active])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Only super_admin can manage users.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
