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
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_partners');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.nav.partners');
    }

    public static function getModelLabel(): string
    {
        return __('admin.partner.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.partner.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make(fn() => __('admin.partner.wizard_general'))
                    ->schema([
                        Forms\Components\TextInput::make('partner_name')
                            ->label(fn() => __('admin.partner.partner_name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('partner_firebase_id')
                            ->label(fn() => __('admin.partner.firebase_id'))
                            ->required()
                            ->maxLength(128)
                            ->helperText(fn() => __('admin.partner.firebase_id_hint')),
                        Forms\Components\TextInput::make('name')
                            ->label(fn() => __('admin.partner.agreement_name'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('billing_email')
                            ->label(fn() => __('admin.partner.billing_email'))
                            ->email()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Wizard\Step::make(fn() => __('admin.partner.wizard_status'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label(fn() => __('admin.common.status'))
                            ->options([
                                'active' => __('admin.common.active'),
                                'paused' => __('admin.common.paused'),
                                'expired' => __('admin.common.expired'),
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(fn() => __('admin.partner.starts_at'))
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(fn() => __('admin.partner.expires_at')),
                        Forms\Components\Textarea::make('notes')
                            ->label(fn() => __('admin.partner.notes'))
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Wizard\Step::make(fn() => __('admin.partner.wizard_economic'))
                    ->description(fn() => __('admin.partner.wizard_economic_desc'))
                    ->schema([
                        Forms\Components\Radio::make('economic_model')
                            ->label(fn() => __('admin.partner.economic_model_apply'))
                            ->options([
                                'commission' => __('admin.partner.model_commission_long'),
                                'sos_call' => __('admin.partner.model_sos_call_long'),
                                'hybrid' => __('admin.partner.model_hybrid_long'),
                            ])
                            ->default('commission')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state === 'sos_call') {
                                    $set('commission_per_call_lawyer', 0);
                                    $set('commission_per_call_expat', 0);
                                    $set('commission_percent', 0);
                                    $set('sos_call_active', true);
                                    return;
                                }
                                if ($state === 'commission') {
                                    $set('sos_call_active', false);
                                    return;
                                }
                                if ($state === 'hybrid') {
                                    $set('sos_call_active', true);
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('sos_call_active'),

                        Forms\Components\Section::make(fn() => __('admin.partner.section_commission'))
                            ->description(fn() => __('admin.partner.section_commission_desc'))
                            ->icon('heroicon-o-banknotes')
                            ->visible(fn(Forms\Get $get) => in_array($get('economic_model'), ['commission', 'hybrid'], true))
                            ->schema([
                                Forms\Components\TextInput::make('commission_per_call_lawyer')
                                    ->label(fn() => __('admin.partner.commission_lawyer'))
                                    ->numeric()
                                    ->default(0)
                                    ->helperText(fn() => __('admin.partner.commission_lawyer_hint')),
                                Forms\Components\TextInput::make('commission_per_call_expat')
                                    ->label(fn() => __('admin.partner.commission_expat'))
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Select::make('commission_type')
                                    ->label(fn() => __('admin.partner.commission_type'))
                                    ->options([
                                        'fixed' => __('admin.partner.commission_fixed'),
                                        'percent' => __('admin.partner.commission_percent'),
                                    ])
                                    ->default('fixed'),
                                Forms\Components\TextInput::make('commission_percent')
                                    ->label(fn() => __('admin.partner.commission_percent_label'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(fn() => __('admin.partner.section_sos_call'))
                            ->description(fn() => __('admin.partner.section_sos_call_desc'))
                            ->icon('heroicon-o-phone')
                            ->visible(fn(Forms\Get $get) => in_array($get('economic_model'), ['sos_call', 'hybrid'], true))
                            ->schema([
                                Forms\Components\TextInput::make('billing_rate')
                                    ->label(fn() => __('admin.partner.billing_rate'))
                                    ->numeric()
                                    ->default(3.00)
                                    ->step(0.01)
                                    ->required(),
                                Forms\Components\Select::make('billing_currency')
                                    ->label(fn() => __('admin.partner.billing_currency'))
                                    ->options([
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ])
                                    ->default('EUR')
                                    ->required(),
                                Forms\Components\TextInput::make('payment_terms_days')
                                    ->label(fn() => __('admin.partner.payment_terms'))
                                    ->numeric()
                                    ->default(15)
                                    ->minValue(0)
                                    ->maxValue(90),
                                Forms\Components\Select::make('call_types_allowed')
                                    ->label(fn() => __('admin.partner.call_types'))
                                    ->options([
                                        'both' => __('admin.partner.call_types_both'),
                                        'expat_only' => __('admin.partner.call_types_expat'),
                                        'lawyer_only' => __('admin.partner.call_types_lawyer'),
                                    ])
                                    ->default('both'),
                            ])
                            ->columns(2),
                    ]),

                Forms\Components\Wizard\Step::make(fn() => __('admin.partner.wizard_quotas'))
                    ->schema([
                        Forms\Components\TextInput::make('max_subscribers')
                            ->label(fn() => __('admin.partner.max_subscribers'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\TextInput::make('max_calls_per_subscriber')
                            ->label(fn() => __('admin.partner.max_calls_per_sub'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\TextInput::make('default_subscriber_duration_days')
                            ->label(fn() => __('admin.partner.default_duration'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(fn() => __('admin.partner.default_duration_hint')),
                        Forms\Components\TextInput::make('max_subscriber_duration_days')
                            ->label(fn() => __('admin.partner.max_duration'))
                            ->numeric()
                            ->minValue(1),
                    ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partner_name')
                    ->label(fn() => __('admin.partner.model_label'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label(fn() => __('admin.partner.firebase_id'))
                    ->searchable()
                    ->limit(15)
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn(string $state) => __('admin.common.' . $state)),
                Tables\Columns\BadgeColumn::make('economic_model')
                    ->label(fn() => __('admin.partner.economic_model'))
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'commission' => __('admin.partner.model_commission'),
                        'sos_call' => __('admin.partner.model_sos_call'),
                        'hybrid' => __('admin.partner.model_hybrid'),
                        default => __('admin.common.dash'),
                    })
                    ->colors([
                        'warning' => 'commission',
                        'success' => 'sos_call',
                        'danger' => 'hybrid',
                    ]),
                Tables\Columns\TextColumn::make('billing_rate')
                    ->label(fn() => __('admin.partner.billing_rate_short'))
                    ->money(fn($record) => $record->billing_currency ?? 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscribers_count')
                    ->label(fn() => __('admin.partner.col_clients'))
                    ->counts('subscribers')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('billing_email')
                    ->label(fn() => __('admin.partner.billing_email_short'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(fn() => __('admin.partner.expires_short'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('admin.partner.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(fn() => __('admin.common.status'))
                    ->options([
                        'active' => __('admin.common.active'),
                        'paused' => __('admin.common.paused'),
                        'expired' => __('admin.common.expired'),
                    ]),
                Tables\Filters\SelectFilter::make('economic_model')
                    ->label(fn() => __('admin.partner.economic_model_filter'))
                    ->options([
                        'commission' => __('admin.partner.filter_commission'),
                        'sos_call' => __('admin.partner.filter_sos_call'),
                        'hybrid' => __('admin.partner.model_hybrid'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleSosCall')
                    ->label(fn($record) => $record->economic_model === 'commission'
                        ? __('admin.partner.action_toggle_to_sos')
                        : __('admin.partner.action_toggle_to_commission'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription(fn($record) => $record->economic_model === 'commission'
                        ? __('admin.partner.toggle_to_sos_desc')
                        : __('admin.partner.toggle_to_commission_desc'))
                    ->action(function ($record) {
                        if ($record->economic_model === 'commission') {
                            $record->update([
                                'economic_model' => 'sos_call',
                                'sos_call_active' => true,
                                'commission_per_call_lawyer' => 0,
                                'commission_per_call_expat' => 0,
                                'commission_percent' => 0,
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

                Tables\Actions\Action::make('suspendAllSubscribers')
                    ->label(fn() => __('admin.partner.action_suspend_all'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn() => __('admin.partner.suspend_all_heading'))
                    ->modalDescription(fn($record) => __('admin.partner.suspend_all_desc', ['partner' => $record->partner_name]))
                    ->modalSubmitActionLabel(fn() => __('admin.partner.suspend_all_submit'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(fn() => __('admin.partner.suspend_reason'))
                            ->required()
                            ->placeholder(fn() => __('admin.partner.suspend_reason_placeholder')),
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
                            ->title(__('admin.partner.suspend_done_title', ['count' => $count]))
                            ->body(__('admin.partner.suspend_done_body', ['partner' => $record->partner_name]))
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('reactivateAllSubscribers')
                    ->label(fn() => __('admin.partner.action_reactivate_all'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn() => __('admin.partner.reactivate_all_heading'))
                    ->modalDescription(fn() => __('admin.partner.reactivate_all_desc'))
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
                            ->title(__('admin.partner.reactivate_done_title', ['count' => $count]))
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
