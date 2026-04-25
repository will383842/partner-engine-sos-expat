<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\TeamMemberResource\Pages;
use App\Models\Subscriber;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service team management for the group-admin partner.
 *
 * The connected user (role='partner', i.e. the company's group admin) can
 * create, list, edit and revoke branch_manager sub-accounts. Each branch
 * manager is automatically scoped to:
 *   • the same partner_firebase_id as the group admin
 *   • the cabinets (group_labels) the group admin assigns to them
 *
 * Hidden entirely from users with role='branch_manager' so they cannot
 * grant themselves more cabinets or create siblings — only the group
 * admin manages the team.
 */
class TeamMemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_account');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.team.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.team.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.team.plural_label');
    }

    /**
     * Visible only to the group admin (role='partner'). Branch managers
     * cannot manage team — they only consume what the group admin grants.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && $user->hasFullPartnerAccess()
            && !empty($user->partner_firebase_id);
    }

    /**
     * Show only this partner's team (other branch_managers under the same
     * partner_firebase_id). Never the group admin themselves, never users
     * from other partners.
     */
    public static function getEloquentQuery(): Builder
    {
        $partnerId = auth()->user()?->partner_firebase_id;
        return parent::getEloquentQuery()
            ->where('partner_firebase_id', $partnerId ?: '__none__')
            ->where('role', User::ROLE_BRANCH_MANAGER);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn() => __('panel.team.section_identity'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(fn() => __('panel.team.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('email')
                        ->label(fn() => __('panel.team.email'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password')
                        ->label(fn() => __('panel.team.password'))
                        ->password()
                        ->revealable()
                        ->minLength(10)
                        ->required(fn(string $operation) => $operation === 'create')
                        ->dehydrated(fn(?string $state) => filled($state))
                        ->dehydrateStateUsing(fn(string $state) => Hash::make($state))
                        ->helperText(fn() => __('panel.team.password_hint')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(fn() => __('panel.team.is_active'))
                        ->default(true)
                        ->helperText(fn() => __('panel.team.is_active_hint')),
                ])
                ->columns(2),

            Forms\Components\Section::make(fn() => __('panel.team.section_scope'))
                ->description(fn() => __('panel.team.section_scope_desc'))
                ->schema([
                    Forms\Components\TagsInput::make('managed_group_labels')
                        ->label(fn() => __('panel.team.managed_group_labels'))
                        ->placeholder(fn() => __('panel.team.managed_group_labels_placeholder'))
                        ->helperText(fn() => __('panel.team.managed_group_labels_hint'))
                        ->suggestions(self::existingGroupLabels())
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('panel.team.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->label(fn() => __('panel.team.email'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage(fn() => __('panel.common.copy_email')),
                Tables\Columns\TextColumn::make('managed_group_labels')
                    ->label(fn() => __('panel.team.managed_group_labels_short'))
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state;
                        }
                        if (is_string($state) && $state !== '') {
                            $decoded = json_decode($state, true);
                            return is_array($decoded) ? $decoded : [];
                        }
                        return [];
                    })
                    ->limitList(4),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('panel.team.col_status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(fn() => __('panel.team.last_login'))
                    ->since()
                    ->placeholder(fn() => __('panel.team.never_logged_in')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('panel.team.created'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(fn() => __('panel.team.filter_active'))
                    ->placeholder(fn() => __('panel.team.filter_all'))
                    ->trueLabel(fn() => __('panel.team.filter_active_only'))
                    ->falseLabel(fn() => __('panel.team.filter_inactive_only')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn($record) => $record->is_active
                        ? __('panel.team.action_deactivate')
                        : __('panel.team.action_activate'))
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn($record) => $record->is_active
                        ? __('panel.team.deactivate_heading')
                        : __('panel.team.activate_heading'))
                    ->modalDescription(fn($record) => $record->is_active
                        ? __('panel.team.deactivate_desc')
                        : __('panel.team.activate_desc'))
                    ->action(fn($record) => $record->update(['is_active' => !$record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label(fn() => __('panel.team.bulk_deactivate'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(fn() => __('panel.team.empty_heading'))
            ->emptyStateDescription(fn() => __('panel.team.empty_desc'))
            ->emptyStateIcon('heroicon-o-user-group');
    }

    /**
     * Distinct group_labels currently used by this partner's subscribers.
     * Surfaced as autocomplete suggestions in the TagsInput so the group
     * admin can pick existing cabinets in one click; new cabinets remain
     * typeable as free text (TagsInput does not lock to suggestions).
     */
    public static function existingGroupLabels(): array
    {
        $partnerId = auth()->user()?->partner_firebase_id;
        if (!$partnerId) {
            return [];
        }
        return Subscriber::where('partner_firebase_id', $partnerId)
            ->whereNotNull('group_label')
            ->where('group_label', '!=', '')
            ->distinct()
            ->orderBy('group_label')
            ->pluck('group_label')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTeamMembers::route('/'),
            'create' => Pages\CreateTeamMember::route('/create'),
            'edit'   => Pages\EditTeamMember::route('/{record}/edit'),
        ];
    }
}
