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
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_config');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.user.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.user.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.user.section_identity'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(fn() => __('admin.user.name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(fn() => __('admin.user.email'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make(__('admin.user.section_access'))
                ->schema([
                    Forms\Components\Select::make('role')
                        ->label(fn() => __('admin.user.role'))
                        ->options([
                            'super_admin' => __('admin.user.role_super_admin_long'),
                            'admin' => __('admin.user.role_admin_long'),
                            'accountant' => __('admin.user.role_accountant_long'),
                            'support' => __('admin.user.role_support_long'),
                        ])
                        ->required()
                        ->default('admin'),
                    Forms\Components\Toggle::make('is_active')
                        ->label(fn() => __('admin.user.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('password')
                        ->label(fn() => __('admin.user.password'))
                        ->password()
                        ->dehydrateStateUsing(fn($state) => !empty($state) ? Hash::make($state) : null)
                        ->dehydrated(fn($state) => !empty($state))
                        ->required(fn(string $context) => $context === 'create')
                        ->minLength(12)
                        ->helperText(fn() => __('admin.user.password_hint')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('admin.user.name_short'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(fn() => __('admin.user.email'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label(fn() => __('admin.user.role'))
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'info' => 'accountant',
                        'gray' => 'support',
                    ])
                    ->formatStateUsing(fn(?string $state) => $state ? __('admin.user.role_' . $state) : __('admin.common.dash')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('admin.user.is_active_short'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(fn() => __('admin.user.last_login'))
                    ->dateTime()
                    ->placeholder(fn() => __('admin.common.never'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.user.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label(fn() => __('admin.user.role'))
                    ->options([
                        'super_admin' => __('admin.user.role_super_admin'),
                        'admin' => __('admin.user.role_admin'),
                        'accountant' => __('admin.user.role_accountant'),
                        'support' => __('admin.user.role_support'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label(fn() => __('admin.user.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn($record) => $record->is_active ? __('admin.user.action_deactivate') : __('admin.user.action_activate'))
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

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
