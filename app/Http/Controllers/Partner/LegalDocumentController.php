<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\PartnerLegalAcceptance;
use App\Models\PartnerLegalDocument;
use App\Services\LegalDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Partner-side legal document management.
 *
 * GET    /api/partner/legal-documents              — list active docs for the connected partner
 * GET    /api/partner/legal-documents/{id}         — detail (HTML body for inline preview)
 * POST   /api/partner/legal-documents/{id}/sign    — record click-wrap acceptance
 * GET    /api/partner/legal-documents/{id}/pdf     — stream the PDF (signed if available, else draft)
 * GET    /api/partner/legal-documents/{id}/proof   — return the eIDAS evidence (json)
 */
class LegalDocumentController extends Controller
{
    public function __construct(protected LegalDocumentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $agreement = Agreement::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->latest()
            ->first();

        if (!$agreement) {
            return response()->json([
                'agreement' => null,
                'legal_status' => 'not_generated',
                'documents' => [],
            ]);
        }

        $docs = PartnerLegalDocument::query()
            ->forAgreement($agreement->id)
            ->active()
            ->with('latestAcceptance')
            ->orderBy('kind')
            ->get();

        return response()->json([
            'agreement' => [
                'id' => $agreement->id,
                'partner_name' => $agreement->partner_name,
                'legal_status' => $agreement->legal_status,
                'legal_signed_at' => $agreement->legal_signed_at,
                'legal_override' => $agreement->legal_override,
                'partner_legal_language' => $agreement->partner_legal_language,
            ],
            'legal_status' => $agreement->legal_status,
            'documents' => $docs->map(fn (PartnerLegalDocument $d) => $this->serializeDocument($d))->all(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $doc = PartnerLegalDocument::forPartner($partnerId)->findOrFail($id);
        $doc->load('latestAcceptance');

        return response()->json([
            'document' => $this->serializeDocument($doc),
            'rendered_html' => $doc->rendered_html,
        ]);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $partnerData = $request->attributes->get('partner_data') ?: [];
        $partnerEmail = $request->attributes->get('firebase_email')
            ?? $partnerData['email']
            ?? null;
        $partnerName = trim(
            ($partnerData['firstName'] ?? '') . ' ' . ($partnerData['lastName'] ?? '')
        ) ?: ($partnerData['displayName'] ?? null);

        $doc = PartnerLegalDocument::forPartner($partnerId)->findOrFail($id);

        if (!$doc->canBeSignedByPartner()) {
            return response()->json([
                'error' => 'document_not_ready',
                'message' => 'Ce document ne peut pas être signé dans son état actuel.',
                'current_status' => $doc->status,
            ], 422);
        }

        $validated = $request->validate([
            'accept' => 'required|accepted',
            'signer_name' => 'nullable|string|max:255',
            'signer_email' => 'nullable|email|max:255',
            'confirm_read' => 'required|accepted',
        ]);

        $signerEmail = $validated['signer_email'] ?? $partnerEmail;
        if (!$signerEmail) {
            return response()->json([
                'error' => 'signer_email_required',
                'message' => 'Une adresse email de signataire est requise.',
            ], 422);
        }

        try {
            $acceptance = $this->service->recordSignature(
                $doc,
                signatureData: [
                    'accepted_by_email' => $signerEmail,
                    'accepted_by_name' => $validated['signer_name'] ?? $partnerName,
                    'accepted_by_firebase_id' => $partnerId,
                    'acceptance_ip' => $request->ip() ?: '0.0.0.0',
                    'acceptance_user_agent' => substr(
                        (string) $request->userAgent(),
                        0,
                        500
                    ) ?: 'unknown',
                ],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'state', 'message' => $e->getMessage()], 422);
        }

        // Email the signer a copy
        try {
            \Illuminate\Support\Facades\Mail::to($signerEmail)
                ->queue(new \App\Mail\PartnerLegalDocumentSignedMail($doc->fresh(['agreement']), $acceptance));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[LegalDocs] Confirmation email failed', [
                'doc_id' => $doc->id, 'err' => $e->getMessage(),
            ]);
        }

        // Telegram notify (admin inbox bot)
        try {
            $this->notifyAdminTelegram($doc, $acceptance);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[LegalDocs] Telegram notify failed', [
                'doc_id' => $doc->id, 'err' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'document' => $this->serializeDocument($doc->fresh(['latestAcceptance'])),
            'acceptance_id' => $acceptance->id,
            'pdf_hash' => $acceptance->signed_pdf_hash ?: $acceptance->pdf_hash,
        ]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $doc = PartnerLegalDocument::forPartner($partnerId)->findOrFail($id);
        $doc->load('latestAcceptance');

        // Prefer the signed PDF (with signature block embedded) if present.
        $path = $doc->latestAcceptance?->signed_pdf_path ?: $doc->pdf_path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json([
                'error' => 'pdf_not_found',
                'message' => 'Le PDF n\'est pas encore disponible. Réessayez dans quelques instants.',
            ], 404);
        }

        $filename = sprintf('%s-v%s.pdf', $doc->kind, $doc->template_version ?: 'na');

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function proof(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $doc = PartnerLegalDocument::forPartner($partnerId)->findOrFail($id);
        $doc->load('latestAcceptance');

        if (!$doc->isSigned() || !$doc->latestAcceptance) {
            return response()->json(['error' => 'not_signed'], 404);
        }

        $a = $doc->latestAcceptance;
        return response()->json([
            'document_kind' => $doc->kind,
            'document_title' => $doc->title,
            'document_version' => $a->document_version,
            'pdf_hash' => $doc->pdf_hash,
            'signed_pdf_hash' => $a->signed_pdf_hash,
            'signed_at' => $a->accepted_at,
            'signed_by_email' => $a->accepted_by_email,
            'signed_by_name' => $a->accepted_by_name,
            'signature_method' => $a->signature_method,
            'acceptance_ip' => $a->acceptance_ip,
            'acceptance_id' => $a->id,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    protected function serializeDocument(PartnerLegalDocument $d): array
    {
        $a = $d->latestAcceptance;
        return [
            'id' => $d->id,
            'kind' => $d->kind,
            'title' => $d->title,
            'language' => $d->language,
            'status' => $d->status,
            'template_version' => $d->template_version,
            'pdf_hash' => $d->pdf_hash,
            'pdf_available' => $d->pdf_path !== null,
            'has_custom_clauses' => is_array($d->custom_clauses) && count($d->custom_clauses) > 0,
            'generated_at' => $d->generated_at,
            'sent_for_signature_at' => $d->sent_for_signature_at,
            'signed_at' => $a?->accepted_at,
            'signed_by_email' => $a?->accepted_by_email,
            'signed_by_name' => $a?->accepted_by_name,
            'signed_pdf_hash' => $a?->signed_pdf_hash,
            'acceptance_id' => $a?->id,
        ];
    }

    protected function notifyAdminTelegram(PartnerLegalDocument $doc, PartnerLegalAcceptance $a): void
    {
        $url = config('services.telegram_engine.url');
        $secret = config('services.telegram_engine.api_key');
        if (!$url || !$secret) return;

        $body = [
            'event' => 'partner_legal_signed',
            'partner_firebase_id' => $doc->partner_firebase_id,
            'agreement_id' => $doc->agreement_id,
            'document_kind' => $doc->kind,
            'document_version' => $doc->template_version,
            'signed_by' => $a->accepted_by_email,
            'acceptance_id' => $a->id,
            'pdf_hash' => $a->signed_pdf_hash ?: $a->pdf_hash,
            'signed_at' => $a->accepted_at?->toIso8601String(),
        ];

        \Illuminate\Support\Facades\Http::withHeaders([
            'X-Engine-Secret' => $secret,
            'Content-Type' => 'application/json',
        ])->timeout(5)->post(
            rtrim($url, '/') . '/api/events/partner-legal-signed',
            $body,
        );
    }
}
