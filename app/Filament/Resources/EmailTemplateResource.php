<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_config');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.nav.email_templates');
    }

    public static function getModelLabel(): string
    {
        return __('admin.email_template.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.email_template.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn() => __('admin.email_template.section_id'))
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(fn() => __('admin.email_template.type'))
                        ->options([
                            'invitation' => __('admin.email_template.type_invitation'),
                            'reminder' => __('admin.email_template.type_reminder'),
                            'expiration' => __('admin.email_template.type_expiration'),
                            'sos_call_activation' => __('admin.email_template.type_sos_call_activation'),
                            'monthly_invoice' => __('admin.email_template.type_monthly_invoice'),
                            'invoice_overdue' => __('admin.email_template.type_invoice_overdue'),
                            'subscriber_magic_link' => __('admin.email_template.type_subscriber_magic_link'),
                        ])
                        ->required(),
                    Forms\Components\Select::make('language')
                        ->label(fn() => __('admin.email_template.language'))
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
                        ->required()
                        ->default('fr'),
                    Forms\Components\TextInput::make('partner_firebase_id')
                        ->label(fn() => __('admin.email_template.partner_optional'))
                        ->helperText(fn() => __('admin.email_template.partner_optional_hint')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(fn() => __('admin.email_template.is_active'))
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make(fn() => __('admin.email_template.section_content'))
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label(fn() => __('admin.email_template.subject'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('body_html')
                        ->label(fn() => __('admin.email_template.body_html'))
                        ->required()
                        ->rows(20)
                        ->helperText(fn() => __('admin.email_template.body_html_hint')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label(fn() => __('admin.email_template.type'))
                    ->colors([
                        'info' => 'invitation',
                        'warning' => ['reminder', 'expiration'],
                        'success' => 'sos_call_activation',
                        'primary' => 'monthly_invoice',
                        'danger' => 'invoice_overdue',
                    ])
                    ->formatStateUsing(fn(string $state) => __('admin.email_template.type_' . $state)),
                Tables\Columns\TextColumn::make('language')
                    ->label(fn() => __('admin.email_template.language'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label(fn() => __('admin.email_template.partner_col'))
                    ->placeholder(fn() => __('admin.common.global'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label(fn() => __('admin.email_template.subject'))
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('admin.email_template.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fn() => __('admin.email_template.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(fn() => __('admin.email_template.type'))
                    ->options([
                        'invitation' => __('admin.email_template.type_invitation'),
                        'reminder' => __('admin.email_template.type_reminder'),
                        'sos_call_activation' => __('admin.email_template.type_sos_call_activation'),
                        'monthly_invoice' => __('admin.email_template.type_monthly_invoice'),
                        'invoice_overdue' => __('admin.email_template.type_invoice_overdue'),
                    ]),
                Tables\Filters\SelectFilter::make('language')
                    ->label(fn() => __('admin.email_template.language'))
                    ->options([
                        'fr' => 'FR', 'en' => 'EN', 'es' => 'ES',
                        'de' => 'DE', 'pt' => 'PT', 'ar' => 'AR',
                        'zh' => 'ZH', 'ru' => 'RU', 'hi' => 'HI',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('type', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
