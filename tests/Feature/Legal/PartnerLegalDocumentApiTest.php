<?php

namespace Tests\Feature\Legal;

use App\Models\Agreement;
use App\Models\LegalDocumentTemplate;
use App\Models\PartnerLegalDocument;
use App\Services\LegalDocumentService;
use Tests\TestCase;

class PartnerLegalDocumentApiTest extends TestCase
{
    private string $partnerId = 'partner_legal_api';

    protected function setUp(): void
    {
        parent::setUp();
        LegalDocumentTemplate::factory()->create();
        LegalDocumentTemplate::factory()->dpa()->create();
        LegalDocumentTemplate::factory()->orderForm()->create();
    }

    public function test_index_returns_documents_for_authenticated_partner(): void
    {
        $this->actingAsPartner($this->partnerId);

        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);
        app(LegalDocumentService::class)->generateDraftsForAgreement($agreement);

        $response = $this->getJson('/api/partner/legal-documents', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('agreement.id', $agreement->id)
            ->assertJsonCount(3, 'documents');
    }

    public function test_index_returns_empty_for_partner_without_agreement(): void
    {
        $this->actingAsPartner($this->partnerId);

        $response = $this->getJson('/api/partner/legal-documents', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('agreement', null)
            ->assertJsonPath('legal_status', 'not_generated')
            ->assertJsonCount(0, 'documents');
    }

    public function test_partner_cannot_access_another_partners_document(): void
    {
        $this->actingAsPartner($this->partnerId);

        $otherAgreement = Agreement::factory()->create([
            'partner_firebase_id' => 'different_partner',
            'status' => 'active',
        ]);
        $docs = app(LegalDocumentService::class)->generateDraftsForAgreement($otherAgreement);
        $otherDoc = $docs[LegalDocumentTemplate::KIND_CGV_B2B];

        $response = $this->getJson(
            "/api/partner/legal-documents/{$otherDoc->id}",
            $this->authHeaders()
        );

        $response->assertStatus(404);
    }

    public function test_partner_can_sign_a_ready_document(): void
    {
        $this->actingAsPartner($this->partnerId, 'signer@partner.com');

        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);
        $service = app(LegalDocumentService::class);
        $docs = $service->generateDraftsForAgreement($agreement);
        $cgv = $service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );

        $response = $this->postJson(
            "/api/partner/legal-documents/{$cgv->id}/sign",
            [
                'accept' => true,
                'confirm_read' => true,
                'signer_name' => 'Jane Signer',
                'signer_email' => 'jane@partner.com',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $cgv->refresh();
        $this->assertSame(PartnerLegalDocument::STATUS_SIGNED, $cgv->status);
        $this->assertCount(1, $cgv->acceptances);

        $acceptance = $cgv->acceptances->first();
        $this->assertSame('jane@partner.com', $acceptance->accepted_by_email);
        $this->assertSame('Jane Signer', $acceptance->accepted_by_name);
        $this->assertNotEmpty($acceptance->acceptance_ip);
        $this->assertNotEmpty($acceptance->acceptance_user_agent);
    }

    public function test_signing_a_draft_document_is_rejected(): void
    {
        $this->actingAsPartner($this->partnerId);

        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);
        $docs = app(LegalDocumentService::class)->generateDraftsForAgreement($agreement);
        $cgv = $docs[LegalDocumentTemplate::KIND_CGV_B2B]; // still in 'draft'

        $response = $this->postJson(
            "/api/partner/legal-documents/{$cgv->id}/sign",
            ['accept' => true, 'confirm_read' => true, 'signer_email' => 'a@b.com'],
            $this->authHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonPath('error', 'document_not_ready');
    }

    public function test_signing_requires_accept_and_confirm_read_checkboxes(): void
    {
        $this->actingAsPartner($this->partnerId);

        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);
        $service = app(LegalDocumentService::class);
        $docs = $service->generateDraftsForAgreement($agreement);
        $cgv = $service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );

        // Missing both checkboxes
        $response = $this->postJson(
            "/api/partner/legal-documents/{$cgv->id}/sign",
            ['signer_email' => 'a@b.com'],
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    public function test_proof_endpoint_returns_eidas_evidence_after_signature(): void
    {
        $this->actingAsPartner($this->partnerId, 'p@x.com');

        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);
        $service = app(LegalDocumentService::class);
        $docs = $service->generateDraftsForAgreement($agreement);
        $cgv = $service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );

        $this->postJson(
            "/api/partner/legal-documents/{$cgv->id}/sign",
            [
                'accept' => true, 'confirm_read' => true,
                'signer_name' => 'X', 'signer_email' => 'x@x.com',
            ],
            $this->authHeaders()
        )->assertStatus(200);

        $proof = $this->getJson(
            "/api/partner/legal-documents/{$cgv->id}/proof",
            $this->authHeaders()
        );

        $proof->assertStatus(200)
            ->assertJsonPath('document_kind', LegalDocumentTemplate::KIND_CGV_B2B)
            ->assertJsonStructure([
                'document_kind', 'document_title', 'document_version',
                'pdf_hash', 'signed_at', 'signed_by_email',
                'signature_method', 'acceptance_ip', 'acceptance_id',
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/partner/legal-documents');
        $response->assertStatus(401);
    }
}
