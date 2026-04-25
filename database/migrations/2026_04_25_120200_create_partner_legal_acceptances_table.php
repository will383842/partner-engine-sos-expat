<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * partner_legal_acceptances — proof-of-signature for each partner_legal_document.
 *
 * One row per acceptance event. Captures eIDAS-recevable click-wrap evidence:
 *   - WHO   : email + name + firebase_id of the human who clicked
 *   - WHEN  : UTC timestamp
 *   - WHERE : IP + user-agent
 *   - WHAT  : pdf hash (SHA-256) — proves no tampering after the fact
 *   - HOW   : signature_method (click_wrap | yousign | docusign)
 *
 * If a future version of a template is published, the partner's prior acceptances
 * remain (historical record), and a new partner_legal_document instance is created
 * with status=pending re-acceptance.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_legal_document_id')
                  ->constrained('partner_legal_documents')->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->string('partner_firebase_id', 128)->index();
            $table->string('kind', 32);
            $table->string('document_version', 16);          // snapshot of template version
            $table->string('pdf_hash', 64);                  // SHA-256 of signed pdf
            $table->string('pdf_path')->nullable();          // signed pdf file path
            $table->string('signed_pdf_path')->nullable();   // pdf with signature block embedded
            $table->string('signed_pdf_hash', 64)->nullable();

            $table->timestamp('accepted_at');                // server UTC clock
            $table->string('accepted_by_email');
            $table->string('accepted_by_name')->nullable();
            $table->string('accepted_by_firebase_id', 128)->nullable();
            $table->string('acceptance_ip', 64)->nullable();
            $table->text('acceptance_user_agent')->nullable();

            $table->string('signature_method', 32)->default('click_wrap');
            $table->string('external_signature_id')->nullable();   // for yousign/docusign envelope
            $table->json('external_signature_payload')->nullable(); // for audit

            $table->timestamps();

            $table->index(['partner_firebase_id', 'kind']);
            $table->index(['agreement_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_legal_acceptances');
    }
};
