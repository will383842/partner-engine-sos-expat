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
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_config');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.nav.api_keys');
    }

    public static function getModelLabel(): string
    {
        return __('admin.api_key.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.api_key.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('partner_firebase_id')
                ->label(fn() => __('admin.api_key.partner'))
                ->options(Agreement::pluck('partner_name', 'partner_firebase_id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label(fn() => __('admin.api_key.name'))
                ->placeholder(fn() => __('admin.api_key.name_placeholder'))
                ->required()
                ->maxLength(100),
            Forms\Components\CheckboxList::make('scopes_array')
                ->label(fn() => __('admin.api_key.scopes'))
                ->options([
                    'subscribers:read' => __('admin.api_key.scope_subs_read'),
                    'subscribers:write' => __('admin.api_key.scope_subs_write'),
                    'activity:read' => __('admin.api_key.scope_activity'),
                    'invoices:read' => __('admin.api_key.scope_invoices'),
                ])
                ->default(['subscribers:write', 'subscribers:read', 'activity:read'])
                ->required()
                ->helperText(fn() => __('admin.api_key.scopes_hint')),
            Forms\Components\Radio::make('environment')
                ->label(fn() => __('admin.api_key.environment'))
                ->options([
                    'live' => __('admin.api_key.env_live'),
                    'test' => __('admin.api_key.env_test'),
                ])
                ->default('live')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prefix')
                    ->label(fn() => __('admin.api_key.col_prefix'))
                    ->fontFamily('mono')
                    ->description(fn($record) => $record->name),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label(fn() => __('admin.api_key.partner'))
                    ->formatStateUsing(fn($state) => Agreement::where('partner_firebase_id', $state)->value('partner_name') ?: $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('scopes')
                    ->label(fn() => __('admin.api_key.col_scopes'))
                    ->badge()
                    ->separator(',')
                    ->limitList(2),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(fn() => __('admin.api_key.col_last_used'))
                    ->since()
                    ->placeholder(fn() => __('admin.common.never')),
                Tables\Columns\IconColumn::make('revoked_at')
                    ->label(fn() => __('admin.api_key.col_revoked'))
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.api_key.col_created'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label(fn() => __('admin.api_key.col_revoked'))
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(fn() => __('admin.api_key.action_revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn() => __('admin.api_key.revoke_desc'))
                    ->visible(fn($record) => !$record->isRevoked())
                    ->action(function ($record) {
                        $record->revoke('admin:filament');
                        Notification::make()->title(__('admin.api_key.revoked_done'))->success()->send();
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
