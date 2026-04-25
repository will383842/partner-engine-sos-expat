<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\LegalDocumentTemplate;
use App\Models\PartnerLegalAcceptance;
use App\Models\PartnerLegalDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Generates, renders, signs and seals legal documents for B2B partners.
 *
 * 3 document kinds (cgv_b2b, dpa, order_form) flow through this service:
 *
 *   generateDraftsForAgreement()
 *     → produces 3 PartnerLegalDocument rows in status='draft' with PDFs.
 *
 *   regenerateDocument()
 *     → recreates a single doc (for example after admin edited custom_clauses).
 *     If the doc was 'signed', the new doc is created and the old one is
 *     marked 'superseded' — historical signatures stay forever.
 *
 *   markValidatedAndSendForSignature()
 *     → admin validates the draft → status='ready_for_signature'.
 *
 *   recordSignature()
 *     → partner clicks "I accept". Atomically: writes acceptance row,
 *     re-renders the PDF with a signature block embedded, hashes it,
 *     stores it, flips doc to 'signed', re-checks agreement.legal_status.
 *
 *   recomputeAgreementLegalStatus()
 *     → looks at all 3 active docs and rolls up status on the agreement.
 *
 * PDF generation is done via barryvdh/laravel-dompdf when available; if not
 * installed, we still create the row with rendered_html stored, and admin
 * sees a clear warning. Same defensive pattern as InvoiceService.
 */
class LegalDocumentService
{
    public const REQUIRED_KINDS = [
        LegalDocumentTemplate::KIND_CGV_B2B,
        LegalDocumentTemplate::KIND_DPA,
        LegalDocumentTemplate::KIND_ORDER_FORM,
    ];

    public function __construct(protected AuditService $audit) {}

    // ────────────────────────────────────────────────────────────────────
    // Generation
    // ────────────────────────────────────────────────────────────────────

    /**
     * Generate (or regenerate) all 3 draft documents for an agreement.
     * Returns the created PartnerLegalDocument rows.
     *
     * If signed docs already exist, they are kept (audit trail) but new drafts
     * are linked through superseded_by_id once they reach 'signed' status.
     */
    public function generateDraftsForAgreement(Agreement $agreement, ?string $actorFirebaseId = null): array
    {
        $created = [];
        DB::transaction(function () use ($agreement, $actorFirebaseId, &$created) {
            foreach (self::REQUIRED_KINDS as $kind) {
                $created[$kind] = $this->createDraft($agreement, $kind, $actorFirebaseId);
            }
            $this->recomputeAgreementLegalStatus($agreement);
        });

        return $created;
    }

    /**
     * Create or replace a single draft document for one (agreement, kind).
     * Any existing non-superseded draft / pending / ready row for that
     * (agreement, kind) is supersided by the new one.
     */
    public function regenerateDocument(
        Agreement $agreement,
        string $kind,
        ?string $actorFirebaseId = null,
        ?array $customClauses = null,
    ): PartnerLegalDocument {
        return DB::transaction(function () use ($agreement, $kind, $actorFirebaseId, $customClauses) {
            $existing = PartnerLegalDocument::query()
                ->forAgreement($agreement->id)
                ->where('kind', $kind)
                ->active()
                ->whereIn('status', [
                    PartnerLegalDocument::STATUS_DRAFT,
                    PartnerLegalDocument::STATUS_PENDING_VALIDATION,
                    PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE,
                ])
                ->get();

            $new = $this->createDraft(
                $agreement,
                $kind,
                $actorFirebaseId,
                $customClauses,
            );

            foreach ($existing as $doc) {
                $doc->status = PartnerLegalDocument::STATUS_SUPERSEDED;
                $doc->superseded_by_id = $new->id;
                $doc->save();
            }

            $this->recomputeAgreementLegalStatus($agreement);
            return $new;
        });
    }

    /**
     * Internal: compose one draft document.
     */
    protected function createDraft(
        Agreement $agreement,
        string $kind,
        ?string $actorFirebaseId,
        ?array $customClauses = null,
    ): PartnerLegalDocument {
        $language = $agreement->partner_legal_language ?: 'fr';
        $template = LegalDocumentTemplate::latestPublished($kind, $language);

        $variables = $this->buildVariables($agreement, $customClauses);
        $title = $this->resolveTitle($kind, $template, $language, $variables);
        $renderedHtml = $this->renderHtml($kind, $template, $variables, $customClauses);

        $doc = PartnerLegalDocument::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'kind' => $kind,
            'language' => $language,
            'template_id' => $template?->id,
            'template_version' => $template?->version,
            'title' => $title,
            'rendered_html' => $renderedHtml,
            'custom_clauses' => $customClauses,
            'rendered_variables' => $variables,
            'status' => PartnerLegalDocument::STATUS_DRAFT,
            'generated_at' => now(),
        ]);

        $this->renderAndStorePdf($doc);

        $this->audit->log(
            actorFirebaseId: $actorFirebaseId ?? 'admin:filament',
            actorRole: 'admin',
            action: 'legal_document_generated',
            resourceType: 'partner_legal_document',
            resourceId: $doc->id,
            details: [
                'agreement_id' => $agreement->id,
                'partner_firebase_id' => $agreement->partner_firebase_id,
                'kind' => $kind,
                'language' => $language,
                'template_version' => $template?->version,
            ],
        );

        return $doc;
    }

    // ────────────────────────────────────────────────────────────────────
    // Variable resolution and HTML rendering
    // ────────────────────────────────────────────────────────────────────

    /**
     * Build the variables snapshot used at generation time.
     * Snapshot makes the document stable even if agreement mutates later.
     */
    public function buildVariables(Agreement $agreement, ?array $customClauses = null): array
    {
        return [
            // Partner & agreement
            'partner_name' => $agreement->partner_name ?? '',
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'agreement_id' => $agreement->id,
            'agreement_name' => $agreement->name ?? '',
            'billing_email' => $agreement->billing_email ?? '',
            'starts_at' => optional($agreement->starts_at)->format('Y-m-d') ?? now()->format('Y-m-d'),
            'expires_at' => optional($agreement->expires_at)->format('Y-m-d') ?? '',

            // Pricing
            'economic_model' => $agreement->economic_model ?? 'commission',
            'billing_rate' => (float) ($agreement->billing_rate ?? 0),
            'monthly_base_fee' => (float) ($agreement->monthly_base_fee ?? 0),
            'pricing_tiers' => $agreement->pricing_tiers ?? [],
            'billing_currency' => $agreement->billing_currency ?? 'EUR',
            'payment_terms_days' => (int) ($agreement->payment_terms_days ?? 15),
            'call_types_allowed' => $agreement->call_types_allowed ?? 'both',

            // Subscriber rules
            'max_subscribers' => (int) ($agreement->max_subscribers ?? 0),
            'default_subscriber_duration_days' => (int) ($agreement->default_subscriber_duration_days ?? 0),
            'max_subscriber_duration_days' => (int) ($agreement->max_subscriber_duration_days ?? 0),

            // Provider (SOS-Expat)
            'provider_legal_name' => config('legal.provider_legal_name', 'World Expat Network SAS'),
            'provider_address' => config('legal.provider_address', ''),
            'provider_siret' => config('legal.provider_siret', ''),
            'provider_vat' => config('legal.provider_vat', ''),
            'provider_email' => config('legal.provider_email', 'contact@sos-expat.com'),
            'provider_dpo_email' => config('legal.provider_dpo_email', 'dpo@sos-expat.com'),
            'provider_jurisdiction' => config('legal.provider_jurisdiction', 'Tribunal de Commerce de Paris'),

            // Custom (per-partner)
            'custom_clauses' => $customClauses ?: [],

            // Generation metadata (visible in PDF)
            'generated_at_iso' => now()->toIso8601String(),
            'generated_at_human' => now()->format('d/m/Y H:i:s') . ' UTC',
            'document_uuid' => (string) Str::uuid(),
        ];
    }

    protected function resolveTitle(string $kind, ?LegalDocumentTemplate $template, string $language, array $vars): string
    {
        if ($template?->title) {
            return $template->title;
        }
        $titles = [
            LegalDocumentTemplate::KIND_CGV_B2B => [
                'fr' => 'Conditions Générales de Vente B2B SOS-Call',
                'en' => 'B2B SOS-Call Terms of Service',
            ],
            LegalDocumentTemplate::KIND_DPA => [
                'fr' => 'Accord de traitement de données (DPA / RGPD art. 28)',
                'en' => 'Data Processing Agreement (DPA / GDPR art. 28)',
            ],
            LegalDocumentTemplate::KIND_ORDER_FORM => [
                'fr' => 'Bon de commande SOS-Call — ' . ($vars['partner_name'] ?? ''),
                'en' => 'SOS-Call Order Form — ' . ($vars['partner_name'] ?? ''),
            ],
        ];
        return $titles[$kind][$language] ?? $titles[$kind]['fr'] ?? ucfirst(str_replace('_', ' ', $kind));
    }

    /**
     * Render the document HTML.
     *
     * Strategy:
     *   - Always wrap in a Blade layout (legal.layout) for header/footer/CSS.
     *   - The body is either:
     *       (a) the published template body_html, with @{{var}} substitution, OR
     *       (b) the default Blade view in resources/views/legal/{kind}.blade.php
     */
    public function renderHtml(string $kind, ?LegalDocumentTemplate $template, array $variables, ?array $customClauses = null): string
    {
        $bodyHtml = $template?->body_html
            ? $this->substituteVariables($template->body_html, $variables)
            : View::make("legal.body.{$kind}", [
                'vars' => $variables,
                'customClauses' => $customClauses ?: [],
            ])->render();

        return View::make('legal.layout', [
            'title' => $this->resolveTitle($kind, $template, $variables['partner_legal_language'] ?? 'fr', $variables),
            'body' => $bodyHtml,
            'vars' => $variables,
            'kind' => $kind,
        ])->render();
    }

    protected function substituteVariables(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $needle = '{{' . $key . '}}';
                $needleSpaces = '{{ ' . $key . ' }}';
                $replace = (string) ($value ?? '');
                $html = str_replace([$needle, $needleSpaces], $replace, $html);
            }
        }
        return $html;
    }

    // ────────────────────────────────────────────────────────────────────
    // PDF rendering & hashing
    // ────────────────────────────────────────────────────────────────────

    /**
     * Render PDF for a doc and store it on the local disk.
     * Updates pdf_path + pdf_hash on the model. Idempotent.
     *
     * Returns the storage path or null if dompdf is not installed.
     */
    public function renderAndStorePdf(PartnerLegalDocument $doc): ?string
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            Log::warning('[LegalDocumentService] dompdf not installed — skipping PDF generation', [
                'document_id' => $doc->id,
                'hint' => 'Run: composer require barryvdh/laravel-dompdf',
            ]);
            return null;
        }

        try {
            $pdfClass = \Barryvdh\DomPDF\Facade\Pdf::class;
            $pdf = $pdfClass::loadHTML($doc->rendered_html)->setPaper('a4', 'portrait');

            $bytes = $pdf->output();
            $hash = hash('sha256', $bytes);

            $path = sprintf(
                'legal_documents/%s/%d/%s_v%s_%s.pdf',
                $doc->partner_firebase_id,
                $doc->agreement_id,
                $doc->kind,
                $doc->template_version ?: 'na',
                Carbon::now()->format('YmdHis'),
            );

            Storage::disk('local')->put($path, $bytes);

            $doc->pdf_path = $path;
            $doc->pdf_hash = $hash;
            $doc->save();

            return $path;
        } catch (\Throwable $e) {
            Log::error('[LegalDocumentService] PDF render failed', [
                'document_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Admin validation flow
    // ────────────────────────────────────────────────────────────────────

    public function markPendingValidation(PartnerLegalDocument $doc, ?string $actor = null): PartnerLegalDocument
    {
        if (!$doc->canBeEdited()) {
            throw new \RuntimeException("Document {$doc->id} is not in an editable state.");
        }
        $doc->status = PartnerLegalDocument::STATUS_PENDING_VALIDATION;
        $doc->save();

        $this->audit->log(
            $actor ?? 'admin:filament',
            'admin',
            'legal_document_pending_validation',
            'partner_legal_document',
            $doc->id,
        );

        $this->recomputeAgreementLegalStatus($doc->agreement);
        return $doc;
    }

    public function markValidatedAndSendForSignature(PartnerLegalDocument $doc, ?string $actor = null): PartnerLegalDocument
    {
        if ($doc->status === PartnerLegalDocument::STATUS_SIGNED) {
            return $doc;
        }
        if (!in_array($doc->status, [
            PartnerLegalDocument::STATUS_DRAFT,
            PartnerLegalDocument::STATUS_PENDING_VALIDATION,
        ], true)) {
            throw new \RuntimeException("Document {$doc->id} cannot be sent for signature from status '{$doc->status}'.");
        }

        $doc->status = PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE;
        $doc->admin_validated_at = now();
        $doc->admin_validated_by = $actor ?? 'admin:filament';
        $doc->sent_for_signature_at = now();
        $doc->save();

        $this->audit->log(
            $actor ?? 'admin:filament',
            'admin',
            'legal_document_ready_for_signature',
            'partner_legal_document',
            $doc->id,
        );

        $this->recomputeAgreementLegalStatus($doc->agreement);
        return $doc;
    }

    /**
     * Bulk: validate all 3 currently-active drafts for an agreement and
     * dispatch the partner notification email.
     */
    public function validateAllAndNotifyPartner(Agreement $agreement, ?string $actor = null): array
    {
        $docs = PartnerLegalDocument::query()
            ->forAgreement($agreement->id)
            ->active()
            ->whereIn('status', [
                PartnerLegalDocument::STATUS_DRAFT,
                PartnerLegalDocument::STATUS_PENDING_VALIDATION,
            ])
            ->get();

        $sent = [];
        foreach ($docs as $doc) {
            $sent[] = $this->markValidatedAndSendForSignature($doc, $actor);
        }

        return $sent;
    }

    // ────────────────────────────────────────────────────────────────────
    // Signature
    // ────────────────────────────────────────────────────────────────────

    /**
     * Record a click-wrap signature for one document.
     * The signed PDF is regenerated with a "signature block" footer
     * containing the captured evidence + sealed hash.
     */
    public function recordSignature(
        PartnerLegalDocument $doc,
        array $signatureData,
        string $method = PartnerLegalAcceptance::METHOD_CLICK_WRAP,
        ?string $externalSignatureId = null,
        ?array $externalPayload = null,
    ): PartnerLegalAcceptance {
        if (!$doc->canBeSignedByPartner()) {
            throw new \RuntimeException("Document {$doc->id} cannot be signed in status '{$doc->status}'.");
        }

        $required = ['accepted_by_email', 'acceptance_ip', 'acceptance_user_agent'];
        foreach ($required as $k) {
            if (!isset($signatureData[$k]) || $signatureData[$k] === '') {
                throw new \InvalidArgumentException("signatureData must include '{$k}'.");
            }
        }

        return DB::transaction(function () use ($doc, $signatureData, $method, $externalSignatureId, $externalPayload) {
            $acceptedAt = Carbon::now('UTC');

            $acceptance = PartnerLegalAcceptance::create([
                'partner_legal_document_id' => $doc->id,
                'agreement_id' => $doc->agreement_id,
                'partner_firebase_id' => $doc->partner_firebase_id,
                'kind' => $doc->kind,
                'document_version' => $doc->template_version ?: 'na',
                'pdf_hash' => $doc->pdf_hash ?: '',
                'pdf_path' => $doc->pdf_path,
                'accepted_at' => $acceptedAt,
                'accepted_by_email' => $signatureData['accepted_by_email'],
                'accepted_by_name' => $signatureData['accepted_by_name'] ?? null,
                'accepted_by_firebase_id' => $signatureData['accepted_by_firebase_id'] ?? null,
                'acceptance_ip' => $signatureData['acceptance_ip'],
                'acceptance_user_agent' => $signatureData['acceptance_user_agent'],
                'signature_method' => $method,
                'external_signature_id' => $externalSignatureId,
                'external_signature_payload' => $externalPayload,
            ]);

            // Re-render PDF with embedded signature block sealed to this acceptance.
            [$signedPath, $signedHash] = $this->renderSignedPdf($doc, $acceptance);
            $acceptance->signed_pdf_path = $signedPath;
            $acceptance->signed_pdf_hash = $signedHash;
            $acceptance->save();

            $doc->status = PartnerLegalDocument::STATUS_SIGNED;
            $doc->save();

            $this->audit->log(
                $signatureData['accepted_by_firebase_id'] ?? 'partner:'.$doc->partner_firebase_id,
                'partner',
                'legal_document_signed',
                'partner_legal_document',
                $doc->id,
                [
                    'kind' => $doc->kind,
                    'agreement_id' => $doc->agreement_id,
                    'acceptance_id' => $acceptance->id,
                    'signature_method' => $method,
                    'pdf_hash' => $doc->pdf_hash,
                    'signed_pdf_hash' => $signedHash,
                ],
                ipAddress: $signatureData['acceptance_ip'] ?? null,
            );

            $this->recomputeAgreementLegalStatus($doc->agreement);
            return $acceptance;
        });
    }

    /**
     * Re-render the PDF with a sealed signature block containing eIDAS evidence.
     * The signed PDF is content-addressable (its own hash is computed and stored).
     */
    protected function renderSignedPdf(PartnerLegalDocument $doc, PartnerLegalAcceptance $acceptance): array
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return [null, null];
        }

        try {
            $signatureBlock = View::make('legal.signature_block', [
                'doc' => $doc,
                'acceptance' => $acceptance,
            ])->render();

            $finalHtml = $doc->rendered_html . $signatureBlock;

            $pdfClass = \Barryvdh\DomPDF\Facade\Pdf::class;
            $pdf = $pdfClass::loadHTML($finalHtml)->setPaper('a4', 'portrait');
            $bytes = $pdf->output();
            $hash = hash('sha256', $bytes);

            $path = sprintf(
                'legal_documents/%s/%d/signed_%s_%d.pdf',
                $doc->partner_firebase_id,
                $doc->agreement_id,
                $doc->kind,
                $acceptance->id,
            );
            Storage::disk('local')->put($path, $bytes);

            return [$path, $hash];
        } catch (\Throwable $e) {
            Log::error('[LegalDocumentService] Signed PDF render failed', [
                'document_id' => $doc->id,
                'acceptance_id' => $acceptance->id,
                'error' => $e->getMessage(),
            ]);
            return [null, null];
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Agreement legal status roll-up
    // ────────────────────────────────────────────────────────────────────

    /**
     * Recompute and persist agreement.legal_status based on the active
     * partner_legal_documents rows.
     */
    public function recomputeAgreementLegalStatus(Agreement $agreement): string
    {
        if ($agreement->legal_override) {
            $agreement->legal_status = Agreement::LEGAL_OVERRIDE;
            $agreement->save();
            return Agreement::LEGAL_OVERRIDE;
        }

        $active = PartnerLegalDocument::query()
            ->forAgreement($agreement->id)
            ->active()
            ->get()
            ->groupBy('kind');

        $present = array_intersect(self::REQUIRED_KINDS, $active->keys()->all());
        if (count($present) < count(self::REQUIRED_KINDS)) {
            $newStatus = empty($present) ? Agreement::LEGAL_NOT_GENERATED : Agreement::LEGAL_DRAFT;
            $agreement->legal_status = $newStatus;
            $agreement->legal_signed_at = null;
            $agreement->save();
            return $newStatus;
        }

        $statuses = [];
        foreach (self::REQUIRED_KINDS as $kind) {
            // For each kind, take the most recent non-superseded doc
            $latest = $active->get($kind)->sortByDesc('id')->first();
            $statuses[$kind] = $latest?->status ?? PartnerLegalDocument::STATUS_DRAFT;
        }

        $allSigned = collect($statuses)->every(fn($s) => $s === PartnerLegalDocument::STATUS_SIGNED);
        $anySigned = collect($statuses)->contains(PartnerLegalDocument::STATUS_SIGNED);
        $allReadyForSig = collect($statuses)->every(fn($s) => in_array($s, [
            PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE,
            PartnerLegalDocument::STATUS_SIGNED,
        ], true));

        if ($allSigned) {
            $agreement->legal_status = Agreement::LEGAL_SIGNED;
            $agreement->legal_signed_at = $agreement->legal_signed_at ?: now();
        } elseif ($anySigned) {
            $agreement->legal_status = Agreement::LEGAL_PARTIALLY_SIGNED;
        } elseif ($allReadyForSig) {
            $agreement->legal_status = Agreement::LEGAL_READY_FOR_SIGNATURE;
        } elseif (collect($statuses)->contains(PartnerLegalDocument::STATUS_PENDING_VALIDATION)) {
            $agreement->legal_status = Agreement::LEGAL_PENDING_VALIDATION;
        } else {
            $agreement->legal_status = Agreement::LEGAL_DRAFT;
        }
        $agreement->save();

        return $agreement->legal_status;
    }
}
