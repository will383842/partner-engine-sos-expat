<?php

namespace App\Filament\Resources\PartnerResource\RelationManagers;

use App\Models\LegalDocumentTemplate;
use App\Models\PartnerLegalDocument;
use App\Services\LegalDocumentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * RelationManager: "Documents légaux" tab on the partner (Agreement) Filament resource.
 *
 * Surfaces, per agreement:
 *   - One row per active legal document (cgv_b2b, dpa, order_form)
 *   - Status badge with full lifecycle (draft → ... → signed)
 *   - Action buttons: generate drafts, edit custom clauses, regenerate one,
 *     validate & send for signature, download PDF, view signature evidence,
 *     legal override (escape hatch with audit log).
 */
class LegalDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'activeLegalDocuments';

    protected static ?string $title = 'Documents légaux';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        // Read-only: docs are NOT created via the form, but via header actions
        // that route through LegalDocumentService.
        return $form->schema([
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\TextInput::make('kind')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('template_version')->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('kind')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        LegalDocumentTemplate::KIND_CGV_B2B => 'CGV B2B',
                        LegalDocumentTemplate::KIND_DPA => 'DPA',
                        LegalDocumentTemplate::KIND_ORDER_FORM => 'Bon de commande',
                        default => $state,
                    })
                    ->colors([
                        'primary' => LegalDocumentTemplate::KIND_CGV_B2B,
                        'warning' => LegalDocumentTemplate::KIND_DPA,
                        'success' => LegalDocumentTemplate::KIND_ORDER_FORM,
                    ]),
                Tables\Columns\TextColumn::make('language')->badge(),
                Tables\Columns\TextColumn::make('template_version')
                    ->label('Version')->badge(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        PartnerLegalDocument::STATUS_DRAFT => 'Brouillon',
                        PartnerLegalDocument::STATUS_PENDING_VALIDATION => 'À valider',
                        PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE => 'Envoyé pour signature',
                        PartnerLegalDocument::STATUS_SIGNED => 'Signé',
                        PartnerLegalDocument::STATUS_SUPERSEDED => 'Remplacé',
                        default => $state,
                    })
                    ->colors([
                        'gray' => PartnerLegalDocument::STATUS_DRAFT,
                        'warning' => PartnerLegalDocument::STATUS_PENDING_VALIDATION,
                        'primary' => PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE,
                        'success' => PartnerLegalDocument::STATUS_SIGNED,
                        'secondary' => PartnerLegalDocument::STATUS_SUPERSEDED,
                    ]),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Généré le')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('latestAcceptance.accepted_at')
                    ->label('Signé le')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('latestAcceptance.accepted_by_email')
                    ->label('Signataire')->placeholder('—')->limit(28),
                Tables\Columns\TextColumn::make('pdf_hash')
                    ->label('Hash')
                    ->formatStateUsing(fn (?string $s) => $s ? substr($s, 0, 10) . '…' : '—')
                    ->tooltip(fn ($record) => $record->pdf_hash),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate_all')
                    ->label('Générer les 3 documents légaux')
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Génère/regénère brouillons CGV B2B + DPA + Bon de commande à partir des paramètres actuels de cet accord. Les documents déjà signés sont conservés (audit trail).')
                    ->form([
                        Forms\Components\Select::make('language')
                            ->label('Langue du partenaire')
                            ->required()
                            ->default(fn () => $this->getOwnerRecord()->partner_legal_language ?? 'fr')
                            ->options([
                                'fr' => 'Français', 'en' => 'English', 'es' => 'Español',
                                'de' => 'Deutsch', 'pt' => 'Português', 'ar' => 'العربية',
                                'zh' => '中文', 'ru' => 'Русский', 'hi' => 'हिन्दी',
                            ]),
                    ])
                    ->action(function (array $data) {
                        $agreement = $this->getOwnerRecord();
                        $agreement->partner_legal_language = $data['language'];
                        $agreement->save();

                        app(LegalDocumentService::class)->generateDraftsForAgreement(
                            $agreement,
                            actorFirebaseId: 'admin:filament',
                        );

                        Notification::make()
                            ->title('3 brouillons générés')
                            ->body('CGV B2B + DPA + Bon de commande créés. Vérifiez le contenu puis cliquez sur "Valider et envoyer pour signature".')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('validate_all_send')
                    ->label('Valider tous & envoyer pour signature')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Valider et notifier le partenaire')
                    ->modalDescription("Vous certifiez avoir relu les 3 documents. Le partenaire recevra un email l'invitant à se connecter à son espace pour signer électroniquement.")
                    ->action(function () {
                        $agreement = $this->getOwnerRecord();
                        $sent = app(LegalDocumentService::class)
                            ->validateAllAndNotifyPartner($agreement, actor: 'admin:filament');

                        if (count($sent) === 0) {
                            Notification::make()
                                ->title('Aucun document à valider')
                                ->body('Aucun brouillon en attente de validation pour cet accord.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Dispatch the partner email
                        try {
                            \Illuminate\Support\Facades\Mail::to($agreement->billing_email)
                                ->queue(new \App\Mail\PartnerLegalDocumentsReadyMail($agreement, $sent));
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('[LegalDocs] Notify email failed', [
                                'agreement_id' => $agreement->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title(count($sent) . ' document(s) envoyé(s) pour signature')
                            ->body('Email de notification expédié à ' . $agreement->billing_email)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('legal_override')
                    ->label('Override légal (contrat papier)')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn () => !$this->getOwnerRecord()->legal_override)
                    ->requiresConfirmation()
                    ->modalHeading('Activer l\'override légal')
                    ->modalDescription("Utiliser uniquement pour les cas où un contrat papier ou un acte notarié a été signé hors plateforme. L'override est tracé en audit log.")
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motif (obligatoire)')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex : contrat papier signé le 22/04/2026, scan archivé sous /shared/legal/contracts/PARTNER-XYZ.pdf'),
                    ])
                    ->action(function (array $data) {
                        $agreement = $this->getOwnerRecord();
                        $agreement->legal_override = true;
                        $agreement->legal_override_reason = $data['reason'];
                        $agreement->legal_override_by = 'admin:filament';
                        $agreement->legal_status = \App\Models\Agreement::LEGAL_OVERRIDE;
                        $agreement->legal_signed_at = now();
                        $agreement->save();

                        app(\App\Services\AuditService::class)->log(
                            'admin:filament', 'admin', 'legal_override_enabled',
                            'agreement', $agreement->id,
                            ['reason' => $data['reason']],
                        );

                        Notification::make()
                            ->title('Override légal activé')
                            ->body('SOS-Call peut désormais être activé pour ce partenaire.')
                            ->success()->send();
                    }),
                Tables\Actions\Action::make('disable_override')
                    ->label('Retirer l\'override légal')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn () => $this->getOwnerRecord()->legal_override)
                    ->requiresConfirmation()
                    ->action(function () {
                        $agreement = $this->getOwnerRecord();
                        $agreement->legal_override = false;
                        $agreement->legal_override_reason = null;
                        $agreement->legal_override_by = null;
                        $agreement->save();
                        app(LegalDocumentService::class)->recomputeAgreementLegalStatus($agreement);

                        app(\App\Services\AuditService::class)->log(
                            'admin:filament', 'admin', 'legal_override_disabled',
                            'agreement', $agreement->id,
                        );

                        Notification::make()->title('Override retiré')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_custom')
                    ->label('Clauses custom')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (PartnerLegalDocument $r) => $r->canBeEdited())
                    ->form([
                        Forms\Components\Repeater::make('custom_clauses')
                            ->label('Clauses particulières (s\'ajoutent à la fin du document)')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Titre')
                                    ->required(),
                                Forms\Components\Textarea::make('content')
                                    ->label('Contenu')
                                    ->required()
                                    ->rows(6),
                            ])
                            ->columnSpanFull()
                            ->collapsed(false)
                            ->defaultItems(0)
                            ->addActionLabel('+ Ajouter une clause'),
                    ])
                    ->fillForm(fn (PartnerLegalDocument $r) => ['custom_clauses' => $r->custom_clauses ?: []])
                    ->action(function (PartnerLegalDocument $record, array $data) {
                        app(LegalDocumentService::class)->regenerateDocument(
                            $record->agreement,
                            $record->kind,
                            actorFirebaseId: 'admin:filament',
                            customClauses: $data['custom_clauses'] ?: null,
                        );
                        Notification::make()
                            ->title('Document régénéré avec les clauses custom')
                            ->success()->send();
                    }),
                Tables\Actions\Action::make('regenerate')
                    ->label('Régénérer')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PartnerLegalDocument $r) => $r->canBeEdited())
                    ->requiresConfirmation()
                    ->action(function (PartnerLegalDocument $record) {
                        app(LegalDocumentService::class)->regenerateDocument(
                            $record->agreement, $record->kind, 'admin:filament', $record->custom_clauses,
                        );
                        Notification::make()->title('Document régénéré')->success()->send();
                    }),
                Tables\Actions\Action::make('send_for_signature')
                    ->label('Envoyer pour signature')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (PartnerLegalDocument $r) => in_array($r->status, [
                        PartnerLegalDocument::STATUS_DRAFT,
                        PartnerLegalDocument::STATUS_PENDING_VALIDATION,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (PartnerLegalDocument $record) {
                        app(LegalDocumentService::class)
                            ->markValidatedAndSendForSignature($record, 'admin:filament');
                        Notification::make()->title('Statut: prêt à signer')->success()->send();
                    }),
                Tables\Actions\Action::make('download')
                    ->label('Télécharger PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PartnerLegalDocument $r) => $r->pdf_path !== null)
                    ->action(function (PartnerLegalDocument $record) {
                        if (!$record->pdf_path || !Storage::disk('local')->exists($record->pdf_path)) {
                            Notification::make()->title('PDF introuvable')->danger()->send();
                            return;
                        }
                        return response()->streamDownload(function () use ($record) {
                            echo Storage::disk('local')->get($record->pdf_path);
                        }, basename($record->pdf_path), ['Content-Type' => 'application/pdf']);
                    }),
                Tables\Actions\Action::make('view_signature')
                    ->label('Voir preuve')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (PartnerLegalDocument $r) => $r->isSigned())
                    ->modalHeading('Preuve de signature électronique')
                    ->modalContent(fn (PartnerLegalDocument $r) => view('legal.signature-evidence', [
                        'doc' => $r,
                        'acceptance' => $r->latestAcceptance,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->paginated(false)
            ->defaultSort('id', 'desc');
    }
}
