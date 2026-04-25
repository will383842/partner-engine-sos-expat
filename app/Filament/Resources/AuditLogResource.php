<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_monitoring');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.nav.audit');
    }

    public static function getModelLabel(): string
    {
        return __('admin.audit.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.audit.date'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('actor_role')
                    ->label(fn() => __('admin.audit.role'))
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'info' => ['accountant', 'support'],
                        'gray' => 'partner',
                    ])
                    ->formatStateUsing(fn(?string $state) => $state ? __('admin.audit.role_' . $state) : __('admin.common.dash')),
                Tables\Columns\TextColumn::make('actor_firebase_id')
                    ->label(fn() => __('admin.audit.actor'))
                    ->limit(20)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('action')
                    ->label(fn() => __('admin.audit.action'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resource_type')
                    ->label(fn() => __('admin.audit.resource_type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('resource_id')
                    ->label(fn() => __('admin.audit.resource_id'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(fn() => __('admin.audit.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_role')
                    ->label(fn() => __('admin.audit.role'))
                    ->options([
                        'super_admin' => __('admin.audit.role_super_admin'),
                        'admin' => __('admin.audit.role_admin'),
                        'accountant' => __('admin.audit.role_accountant'),
                        'support' => __('admin.audit.role_support'),
                        'partner' => __('admin.audit.role_partner'),
                        'system' => __('admin.audit.role_system'),
                    ]),
                Tables\Filters\Filter::make('action')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('action')->label(fn() => __('admin.audit.action_partial')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['action'] ?? null, fn($q, $a) => $q->where('action', 'like', "%{$a}%"));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
