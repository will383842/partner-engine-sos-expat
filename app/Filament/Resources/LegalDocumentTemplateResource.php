<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalDocumentTemplateResource\Pages;
use App\Models\LegalDocumentTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LegalDocumentTemplateResource extends Resource
{
    protected static ?string $model = LegalDocumentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_legal');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.legal.templates_nav');
    }

    public static function getModelLabel(): string
    {
        return __('admin.legal.template_model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.legal.templates_plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.legal.section_meta'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('kind')
                        ->label(__('admin.legal.kind'))
                        ->required()
                        ->options([
                            LegalDocumentTemplate::KIND_CGV_B2B => __('admin.legal.kind_cgv_b2b'),
                            LegalDocumentTemplate::KIND_DPA => __('admin.legal.kind_dpa'),
                            LegalDocumentTemplate::KIND_ORDER_FORM => __('admin.legal.kind_order_form'),
                        ]),
                    Forms\Components\Select::make('language')
                        ->label(__('admin.legal.language'))
                        ->required()
                        ->default('fr')
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
                        ]),
                    Forms\Components\TextInput::make('version')
                        ->label(__('admin.legal.version'))
                        ->required()
                        ->placeholder('1.0.0')
                        ->helperText(__('admin.legal.version_hint')),
                ]),

            Forms\Components\Section::make(__('admin.legal.section_content'))
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('admin.legal.title'))
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('body_html_notice')
                        ->label('')
                        ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                            '<div style="background:#eef2ff;border-left:3px solid #6366f1;padding:.6rem .8rem;border-radius:.25rem;font-size:.85rem;color:#3730a3;">'
                            . '<strong>📄 Champ optionnel.</strong> Laissez <em>vide</em> pour utiliser le contenu de référence livré dans le code '
                            . '(<code>resources/views/legal/body/' . ($record?->kind ?? '{kind}') . '.blade.php</code>) — recommandé tant que vous n\'avez pas besoin de personnalisation.<br>'
                            . 'Remplissez ce champ uniquement pour <strong>surcharger</strong> le contenu par défaut. Les directives Blade (<code>@if</code>, <code>@foreach</code>) ne sont alors plus exécutées : utilisez uniquement les variables <code>{{nom_variable}}</code>.<br>'
                            . 'Cliquez « Aperçu » dans la liste pour voir le rendu (par défaut ou personnalisé) avec des valeurs d\'exemple.'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('body_html')
                        ->label(__('admin.legal.body_html'))
                        ->helperText(__('admin.legal.body_html_hint'))
                        ->placeholder('Laissez vide pour utiliser le contenu par défaut, ou collez votre HTML personnalisé ici.')
                        ->rows(20)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('change_notes')
                        ->label(__('admin.legal.change_notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('admin.legal.section_publication'))
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label(__('admin.legal.is_published'))
                        ->helperText(__('admin.legal.is_published_hint'))
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('published_at', now());
                            } else {
                                $set('published_at', null);
                            }
                        }),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label(__('admin.legal.published_at')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('kind')
                    ->label(__('admin.legal.kind'))
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        LegalDocumentTemplate::KIND_CGV_B2B => __('admin.legal.kind_cgv_b2b'),
                        LegalDocumentTemplate::KIND_DPA => __('admin.legal.kind_dpa'),
                        LegalDocumentTemplate::KIND_ORDER_FORM => __('admin.legal.kind_order_form'),
                        default => $state,
                    })
                    ->colors([
                        'primary' => LegalDocumentTemplate::KIND_CGV_B2B,
                        'warning' => LegalDocumentTemplate::KIND_DPA,
                        'success' => LegalDocumentTemplate::KIND_ORDER_FORM,
                    ]),
                Tables\Columns\TextColumn::make('language')
                    ->label(__('admin.legal.language'))
                    ->badge(),
                Tables\Columns\TextColumn::make('version')
                    ->label(__('admin.legal.version'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.legal.title'))
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('body_source')
                    ->label('Contenu')
                    ->getStateUsing(fn (LegalDocumentTemplate $record) => filled($record->body_html) ? 'custom' : 'default')
                    ->formatStateUsing(fn (string $state) => $state === 'custom' ? 'Personnalisé' : 'Défaut (code)')
                    ->colors([
                        'gray' => 'default',
                        'warning' => 'custom',
                    ]),
                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('admin.legal.published'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('admin.legal.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        LegalDocumentTemplate::KIND_CGV_B2B => __('admin.legal.kind_cgv_b2b'),
                        LegalDocumentTemplate::KIND_DPA => __('admin.legal.kind_dpa'),
                        LegalDocumentTemplate::KIND_ORDER_FORM => __('admin.legal.kind_order_form'),
                    ]),
                Tables\Filters\SelectFilter::make('language')
                    ->options(['fr' => 'FR', 'en' => 'EN', 'es' => 'ES']),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label(__('admin.legal.published')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label(__('admin.legal.preview'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (LegalDocumentTemplate $record) => $record->title)
                    ->modalContent(fn (LegalDocumentTemplate $record) => view('legal.preview-template', ['template' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.common.close')),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalDocumentTemplates::route('/'),
            'create' => Pages\CreateLegalDocumentTemplate::route('/create'),
            'edit' => Pages\EditLegalDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
