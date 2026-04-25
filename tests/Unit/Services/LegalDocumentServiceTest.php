<?php

namespace Tests\Unit\Services;

use App\Models\Agreement;
use App\Models\LegalDocumentTemplate;
use App\Models\PartnerLegalAcceptance;
use App\Models\PartnerLegalDocument;
use App\Services\LegalDocumentService;
use Tests\TestCase;

class LegalDocumentServiceTest extends TestCase
{
    private LegalDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LegalDocumentService::class);

        // Seed published templates so generateDraftsForAgreement can resolve them.
        LegalDocumentTemplate::factory()->create();              // cgv_b2b
        LegalDocumentTemplate::factory()->dpa()->create();        // dpa
        LegalDocumentTemplate::factory()->orderForm()->create();  // order_form
    }

    public function test_generates_three_drafts_for_agreement(): void
    {
        $agreement = Agreement::factory()->create();

        $docs = $this->service->generateDraftsForAgreement($agreement);

        $this->assertCount(3, $docs);
        $this->assertArrayHasKey(LegalDocumentTemplate::KIND_CGV_B2B, $docs);
        $this->assertArrayHasKey(LegalDocumentTemplate::KIND_DPA, $docs);
        $this->assertArrayHasKey(LegalDocumentTemplate::KIND_ORDER_FORM, $docs);

        foreach ($docs as $doc) {
            $this->assertSame(PartnerLegalDocument::STATUS_DRAFT, $doc->status);
            $this->assertNotEmpty($doc->rendered_html);
            $this->assertNotNull($doc->generated_at);
            $this->assertSame($agreement->partner_firebase_id, $doc->partner_firebase_id);
        }

        $agreement->refresh();
        $this->assertSame(Agreement::LEGAL_DRAFT, $agreement->legal_status);
    }

    public function test_template_variable_substitution_works(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_name' => 'Acme Corp',
            'billing_rate' => 5.50,
            'billing_currency' => 'EUR',
        ]);

        $docs = $this->service->generateDraftsForAgreement($agreement);
        $cgv = $docs[LegalDocumentTemplate::KIND_CGV_B2B];

        $this->assertStringContainsString('Acme Corp', $cgv->rendered_html);
    }

    public function test_regenerate_supersedes_previous_draft(): void
    {
        $agreement = Agreement::factory()->create();
        $first = $this->service->generateDraftsForAgreement($agreement);
        $oldDpa = $first[LegalDocumentTemplate::KIND_DPA];

        $newDpa = $this->service->regenerateDocument(
            $agreement,
            LegalDocumentTemplate::KIND_DPA,
            customClauses: [['title' => 'X', 'content' => 'Y']],
        );

        $oldDpa->refresh();
        $this->assertSame(PartnerLegalDocument::STATUS_SUPERSEDED, $oldDpa->status);
        $this->assertSame($newDpa->id, $oldDpa->superseded_by_id);
        $this->assertNotEquals($oldDpa->id, $newDpa->id);
        $this->assertSame(PartnerLegalDocument::STATUS_DRAFT, $newDpa->status);
    }

    public function test_validate_and_send_for_signature_changes_status(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);
        $cgv = $docs[LegalDocumentTemplate::KIND_CGV_B2B];

        $updated = $this->service->markValidatedAndSendForSignature($cgv, 'admin:test');

        $this->assertSame(PartnerLegalDocument::STATUS_READY_FOR_SIGNATURE, $updated->status);
        $this->assertNotNull($updated->admin_validated_at);
        $this->assertNotNull($updated->sent_for_signature_at);
        $this->assertSame('admin:test', $updated->admin_validated_by);
    }

    public function test_record_signature_creates_acceptance_with_full_evidence(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);
        $cgv = $this->service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );

        $acceptance = $this->service->recordSignature($cgv, [
            'accepted_by_email' => 'signer@example.com',
            'accepted_by_name' => 'Jane Doe',
            'accepted_by_firebase_id' => 'fb_signer_1',
            'acceptance_ip' => '203.0.113.42',
            'acceptance_user_agent' => 'Mozilla/5.0 Test',
        ]);

        $this->assertInstanceOf(PartnerLegalAcceptance::class, $acceptance);
        $this->assertSame('signer@example.com', $acceptance->accepted_by_email);
        $this->assertSame('Jane Doe', $acceptance->accepted_by_name);
        $this->assertSame('203.0.113.42', $acceptance->acceptance_ip);
        $this->assertSame(PartnerLegalAcceptance::METHOD_CLICK_WRAP, $acceptance->signature_method);
        $this->assertNotNull($acceptance->accepted_at);

        $cgv->refresh();
        $this->assertSame(PartnerLegalDocument::STATUS_SIGNED, $cgv->status);
    }

    public function test_signature_requires_evidence_fields(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);
        $cgv = $this->service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->service->recordSignature($cgv, [
            // Missing acceptance_ip and accepted_by_email
        ]);
    }

    public function test_cannot_sign_document_in_draft_state(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);
        $cgv = $docs[LegalDocumentTemplate::KIND_CGV_B2B];

        $this->expectException(\RuntimeException::class);

        $this->service->recordSignature($cgv, [
            'accepted_by_email' => 'x@x.com',
            'acceptance_ip' => '1.2.3.4',
            'acceptance_user_agent' => 'ua',
        ]);
    }

    public function test_agreement_legal_status_rolls_up_to_signed_when_all_three_signed(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);

        foreach ($docs as $doc) {
            $ready = $this->service->markValidatedAndSendForSignature($doc, 'admin:test');
            $this->service->recordSignature($ready, [
                'accepted_by_email' => 'partner@x.com',
                'acceptance_ip' => '1.1.1.1',
                'acceptance_user_agent' => 'ua',
            ]);
        }

        $agreement->refresh();
        $this->assertSame(Agreement::LEGAL_SIGNED, $agreement->legal_status);
        $this->assertNotNull($agreement->legal_signed_at);
        $this->assertTrue($agreement->isLegallyCleared());
    }

    public function test_partially_signed_status_when_only_some_docs_signed(): void
    {
        $agreement = Agreement::factory()->create();
        $docs = $this->service->generateDraftsForAgreement($agreement);

        $cgv = $this->service->markValidatedAndSendForSignature(
            $docs[LegalDocumentTemplate::KIND_CGV_B2B],
            'admin:test'
        );
        $this->service->recordSignature($cgv, [
            'accepted_by_email' => 'p@x.com',
            'acceptance_ip' => '1.1.1.1',
            'acceptance_user_agent' => 'ua',
        ]);

        $agreement->refresh();
        $this->assertSame(Agreement::LEGAL_PARTIALLY_SIGNED, $agreement->legal_status);
        $this->assertFalse($agreement->isLegallyCleared());
    }

    public function test_agreement_observer_blocks_sos_call_active_without_signed_docs(): void
    {
        $agreement = Agreement::factory()->create([
            'sos_call_active' => false,
            'legal_status' => Agreement::LEGAL_DRAFT,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/n\'a pas encore signé/');

        $agreement->sos_call_active = true;
        $agreement->save();
    }

    public function test_agreement_observer_allows_sos_call_active_with_legal_override(): void
    {
        $agreement = Agreement::factory()->create([
            'sos_call_active' => false,
            'legal_override' => true,
            'legal_status' => Agreement::LEGAL_OVERRIDE,
        ]);

        $agreement->sos_call_active = true;
        $agreement->save();

        $this->assertTrue($agreement->fresh()->sos_call_active);
    }

    public function test_partner_isolation_via_for_partner_scope(): void
    {
        $a1 = Agreement::factory()->create(['partner_firebase_id' => 'partner_aaa']);
        $a2 = Agreement::factory()->create(['partner_firebase_id' => 'partner_bbb']);

        $this->service->generateDraftsForAgreement($a1);
        $this->service->generateDraftsForAgreement($a2);

        $aaaDocs = PartnerLegalDocument::query()->forPartner('partner_aaa')->get();
        $this->assertCount(3, $aaaDocs);
        foreach ($aaaDocs as $doc) {
            $this->assertSame('partner_aaa', $doc->partner_firebase_id);
        }
    }
}
