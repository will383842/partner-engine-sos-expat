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
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_partners');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.subscriber.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.subscriber.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.subscriber.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn() => __('admin.subscriber.section_partner'))
                ->schema([
                    Forms\Components\Select::make('agreement_id')
                        ->label(fn() => __('admin.subscriber.agreement'))
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
                        ->label(fn() => __('admin.subscriber.partner_firebase_id'))
                        ->required()
                        ->maxLength(128),
                ])->columns(2),

            Forms\Components\Section::make(fn() => __('admin.subscriber.section_profile'))
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label(fn() => __('admin.subscriber.first_name'))
                        ->maxLength(100),
                    Forms\Components\TextInput::make('last_name')
                        ->label(fn() => __('admin.subscriber.last_name'))
                        ->maxLength(100),
                    Forms\Components\TextInput::make('email')
                        ->label(fn() => __('admin.subscriber.email'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(fn() => __('admin.subscriber.phone'))
                        ->helperText(fn() => __('admin.subscriber.phone_hint'))
                        ->maxLength(20),
                    Forms\Components\TextInput::make('country')
                        ->label(fn() => __('admin.subscriber.country'))
                        ->maxLength(10),
                    Forms\Components\Select::make('language')
                        ->label(fn() => __('admin.subscriber.language'))
                        ->options([
                            'fr' => __('admin.common.lang_fr'),
                            'en' => __('admin.common.lang_en'),
                            'es' => __('admin.common.lang_es'),
                            'de' => __('admin.common.lang_de'),
                            'pt' => __('admin.common.lang_pt'),
                            'ar' => __('admin.common.lang_ar'),
                            'zh' => __('admin.common.lang_zh'),
                            'ru' => __('admin.common.lang_ru'),
                            'hi' => __('admin.common.lang_hi'),
                        ])
                        ->default('fr'),
                ])->columns(2),

            Forms\Components\Section::make(fn() => __('admin.subscriber.section_hierarchy'))
                ->description(fn() => __('admin.subscriber.section_hierarchy_desc'))
                ->schema([
                    Forms\Components\TextInput::make('group_label')
                        ->label(fn() => __('admin.subscriber.group_label'))
                        ->helperText(fn() => __('admin.subscriber.group_label_hint'))
                        ->maxLength(120),
                    Forms\Components\TextInput::make('region')
                        ->label(fn() => __('admin.subscriber.region'))
                        ->helperText(fn() => __('admin.subscriber.region_hint'))
                        ->maxLength(120),
                    Forms\Components\TextInput::make('department')
                        ->label(fn() => __('admin.subscriber.department'))
                        ->helperText(fn() => __('admin.subscriber.department_hint'))
                        ->maxLength(120),
                    Forms\Components\TextInput::make('external_id')
                        ->label(fn() => __('admin.subscriber.external_id'))
                        ->helperText(fn() => __('admin.subscriber.external_id_hint'))
                        ->maxLength(255),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make(fn() => __('admin.subscriber.section_sos_call'))
                ->schema([
                    Forms\Components\TextInput::make('sos_call_code')
                        ->label(fn() => __('admin.subscriber.sos_call_code'))
                        ->helperText(fn() => __('admin.subscriber.sos_call_code_hint'))
                        ->maxLength(20),
                    Forms\Components\DateTimePicker::make('sos_call_activated_at')
                        ->label(fn() => __('admin.subscriber.sos_call_activated_at')),
                    Forms\Components\DateTimePicker::make('sos_call_expires_at')
                        ->label(fn() => __('admin.subscriber.sos_call_expires_at')),
                    Forms\Components\Select::make('status')
                        ->label(fn() => __('admin.common.status'))
                        ->options([
                            'invited' => __('admin.common.invited'),
                            'active' => __('admin.common.active'),
                            'suspended' => __('admin.common.suspended'),
                            'expired' => __('admin.common.expired'),
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
                    ->label(fn() => __('admin.partner.model_label'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label(fn() => __('admin.subscriber.first_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label(fn() => __('admin.subscriber.last_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(fn() => __('admin.subscriber.email'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(fn() => __('admin.subscriber.phone_short'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sos_call_code')
                    ->label(fn() => __('admin.subscriber.sos_call_code'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage(fn() => __('admin.common.copy_code'))
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('group_label')
                    ->label(fn() => __('admin.subscriber.group_label_short'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\TextColumn::make('region')
                    ->label(fn() => __('admin.subscriber.region'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\TextColumn::make('department')
                    ->label(fn() => __('admin.subscriber.department_short'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\TextColumn::make('external_id')
                    ->label(fn() => __('admin.subscriber.external_id_short'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder(fn() => __('admin.common.dash')),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'invited',
                        'danger' => ['suspended', 'expired'],
                    ])
                    ->formatStateUsing(fn(string $state) => __('admin.common.' . $state)),
                Tables\Columns\TextColumn::make('calls_expert')
                    ->label(fn() => __('admin.subscriber.calls_expert'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('calls_lawyer')
                    ->label(fn() => __('admin.subscriber.calls_lawyer'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sos_call_expires_at')
                    ->label(fn() => __('admin.subscriber.sos_call_expires_short'))
                    ->date()
                    ->placeholder(fn() => __('admin.common.permanent'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.subscriber.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->options([
                        'invited' => __('admin.common.invited'),
                        'active' => __('admin.common.active'),
                        'suspended' => __('admin.common.suspended'),
                        'expired' => __('admin.common.expired'),
                    ]),
                Tables\Filters\SelectFilter::make('agreement_id')
                    ->label(fn() => __('admin.partner.model_label'))
                    ->options(Agreement::pluck('partner_name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('has_sos_code')
                    ->label(fn() => __('admin.subscriber.filter_has_code'))
                    ->query(fn($query) => $query->whereNotNull('sos_call_code')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label(fn() => __('admin.subscriber.action_suspend'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'active')
                    ->action(fn($record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('reactivate')
                    ->label(fn() => __('admin.subscriber.action_reactivate'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'suspended')
                    ->action(fn($record) => $record->update(['status' => 'active'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('suspend')
                        ->label(fn() => __('admin.subscriber.action_suspend'))
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
