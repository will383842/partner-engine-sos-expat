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
    protected static ?string $navigationGroup = 'Configuration';
    protected static ?string $navigationLabel = 'Templates email';
    protected static ?string $modelLabel = 'Template email';
    protected static ?string $pluralModelLabel = 'Templates email';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'invitation' => 'Invitation',
                            'reminder' => 'Rappel',
                            'expiration' => 'Expiration',
                            'sos_call_activation' => 'Activation SOS-Call',
                            'monthly_invoice' => 'Facture mensuelle',
                            'invoice_overdue' => 'Facture en retard',
                            'subscriber_magic_link' => 'Magic link subscriber',
                        ])
                        ->required(),
                    Forms\Components\Select::make('language')
                        ->label('Langue')
                        ->options([
                            'fr' => 'Français',
                            'en' => 'English',
                            'es' => 'Español',
                            'de' => 'Deutsch',
                            'pt' => 'Português',
                            'ar' => 'العربية',
                            'zh' => '中文',
                            'ru' => 'Русский',
                            'hi' => 'हिन्दी',
                        ])
                        ->required()
                        ->default('fr'),
                    Forms\Components\TextInput::make('partner_firebase_id')
                        ->label('Partenaire (vide = global)')
                        ->helperText('Laissez vide pour utiliser ce template comme défaut global'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Actif')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Contenu')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Objet')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('body_html')
                        ->label('Corps HTML')
                        ->required()
                        ->rows(20)
                        ->helperText('Variables disponibles: {first_name}, {partner_name}, {sos_call_code}, {expires_at}, {invoice_number}, {total_amount}, etc.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'info' => 'invitation',
                        'warning' => ['reminder', 'expiration'],
                        'success' => 'sos_call_activation',
                        'primary' => 'monthly_invoice',
                        'danger' => 'invoice_overdue',
                    ]),
                Tables\Columns\TextColumn::make('language')
                    ->label('Langue')
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner_firebase_id')
                    ->label('Partenaire')
                    ->placeholder('🌐 Global')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Objet')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'invitation' => 'Invitation',
                        'reminder' => 'Rappel',
                        'sos_call_activation' => 'Activation SOS-Call',
                        'monthly_invoice' => 'Facture mensuelle',
                        'invoice_overdue' => 'Facture en retard',
                    ]),
                Tables\Filters\SelectFilter::make('language')
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
