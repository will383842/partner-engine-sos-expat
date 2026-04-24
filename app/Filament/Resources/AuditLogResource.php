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
    protected static ?string $navigationGroup = 'Surveillance';
    protected static ?string $navigationLabel = 'Audit logs';
    protected static ?string $modelLabel = 'Audit log';
    protected static ?string $pluralModelLabel = 'Audit logs';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('actor_role')
                    ->label('Rôle')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'info' => ['accountant', 'support'],
                        'gray' => 'partner',
                    ]),
                Tables\Columns\TextColumn::make('actor_firebase_id')
                    ->label('Acteur')
                    ->limit(20)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Type ressource')
                    ->searchable(),
                Tables\Columns\TextColumn::make('resource_id')
                    ->label('ID ressource')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'accountant' => 'Accountant',
                        'support' => 'Support',
                        'partner' => 'Partner',
                        'system' => 'System',
                    ]),
                Tables\Filters\Filter::make('action')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('action')->label('Action (partiel)'),
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
