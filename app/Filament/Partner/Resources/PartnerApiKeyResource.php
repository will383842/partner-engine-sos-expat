<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\PartnerApiKeyResource\Pages;
use App\Models\PartnerApiKey;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Self-service API keys for partners.
 *
 * Each partner can create/revoke their own keys (scoped to their
 * partner_firebase_id) — no admin involvement needed for routine
 * CRM/integration tokens.
 *
 * Plain tokens are shown ONCE at creation time via a persistent
 * notification, then never accessible again.
 */
class PartnerApiKeyResource extends Resource
{
    protected static ?string $model = PartnerApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_account');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.api_key.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.api_key.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.api_key.plural_label');
    }

    /**
     * Hidden from branch managers entirely. An API key generated from a
     * branch_manager session would carry the partner_firebase_id and
     * therefore see every subscriber of the partner via /api/v1, bypassing
     * the cabinet scoping enforced in the UI. Only the group admin
     * (role=partner) can mint partner-wide keys.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && $user->hasFullPartnerAccess()
            && !empty($user->partner_firebase_id);
    }

    /**
     * Scope all queries to the current partner's keys only.
     */
    public static function getEloquentQuery(): Builder
    {
        $partnerId = auth()->user()?->partner_firebase_id;
        return parent::getEloquentQuery()
            ->where('partner_firebase_id', $partnerId ?: '__none__');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(fn() => __('panel.api_key.name'))
                ->placeholder(fn() => __('panel.api_key.name_placeholder'))
                ->required()
                ->maxLength(100)
                ->helperText(fn() => __('panel.api_key.name_hint')),
            Forms\Components\CheckboxList::make('scopes_array')
                ->label(fn() => __('panel.api_key.scopes'))
                ->options([
                    'subscribers:read' => __('panel.api_key.scope_subs_read'),
                    'subscribers:write' => __('panel.api_key.scope_subs_write'),
                    'activity:read' => __('panel.api_key.scope_activity'),
                    'invoices:read' => __('panel.api_key.scope_invoices'),
                ])
                ->default(['subscribers:write', 'subscribers:read', 'activity:read'])
                ->required()
                ->helperText(fn() => __('panel.api_key.scopes_hint')),
            Forms\Components\Radio::make('environment')
                ->label(fn() => __('panel.api_key.environment'))
                ->options([
                    'live' => __('panel.api_key.env_live'),
                    'test' => __('panel.api_key.env_test'),
                ])
                ->default('live')
                ->required()
                ->helperText(fn() => __('panel.api_key.environment_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prefix')
                    ->label(fn() => __('panel.api_key.col_prefix'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage(fn() => __('panel.api_key.prefix_copied'))
                    ->description(fn($record) => $record->name),
                Tables\Columns\TextColumn::make('scopes')
                    ->label(fn() => __('panel.api_key.col_scopes'))
                    ->badge()
                    ->separator(',')
                    ->limitList(3),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(fn() => __('panel.api_key.col_last_used'))
                    ->since()
                    ->placeholder(fn() => __('panel.api_key.never_used')),
                Tables\Columns\IconColumn::make('revoked_at')
                    ->label(fn() => __('panel.api_key.col_status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn($record) => $record->revoked_at
                        ? __('panel.api_key.status_revoked')
                        : __('panel.api_key.status_active')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('panel.api_key.col_created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label(fn() => __('panel.api_key.filter_revoked'))
                    ->nullable()
                    ->placeholder(fn() => __('panel.api_key.filter_all'))
                    ->trueLabel(fn() => __('panel.api_key.filter_revoked_only'))
                    ->falseLabel(fn() => __('panel.api_key.filter_active_only')),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(fn() => __('panel.api_key.action_revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn() => __('panel.api_key.revoke_heading'))
                    ->modalDescription(fn() => __('panel.api_key.revoke_desc'))
                    ->modalSubmitActionLabel(fn() => __('panel.api_key.revoke_confirm'))
                    ->visible(fn($record) => !$record->isRevoked())
                    ->action(function ($record) {
                        $actor = 'partner:' . (auth()->id() ?: 'unknown');
                        $record->revoke($actor);
                        Notification::make()
                            ->title(__('panel.api_key.revoked_done'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(fn() => __('panel.api_key.empty_heading'))
            ->emptyStateDescription(fn() => __('panel.api_key.empty_desc'))
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerApiKeys::route('/'),
            'create' => Pages\CreatePartnerApiKey::route('/create'),
        ];
    }
}
