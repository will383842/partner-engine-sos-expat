<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * partner_legal_documents — concrete document instances generated PER PARTNER.
 *
 * For each partner agreement we create up to 3 documents:
 *   - one CGV B2B       (rendered from current published cgv_b2b template)
 *   - one DPA           (rendered from current published dpa template)
 *   - one order form    (rendered from agreement parameters + custom clauses)
 *
 * Lifecycle:
 *   draft → pending_admin_validation → ready_for_signature → signed
 *   (signed rows can be 'superseded' if a new version is generated)
 *
 * sos_call_active on the agreement is gated by these 3 docs being 'signed'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->string('partner_firebase_id', 128)->index();
            $table->string('kind', 32);                       // cgv_b2b | dpa | order_form
            $table->string('language', 8)->default('fr');
            $table->foreignId('template_id')->nullable()
                  ->constrained('legal_document_templates')->nullOnDelete();
            $table->string('template_version', 16)->nullable(); // snapshot of source version
            $table->string('title');
            $table->longText('rendered_html');                // frozen HTML at generation time
            $table->json('custom_clauses')->nullable();       // admin per-partner overrides
            $table->json('rendered_variables')->nullable();   // resolved vars snapshot
            $table->string('status', 32)->default('draft');
            $table->string('pdf_path')->nullable();           // storage/local path
            $table->string('pdf_hash', 64)->nullable();       // SHA-256 of pdf bytes
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('admin_validated_at')->nullable();
            $table->string('admin_validated_by')->nullable();
            $table->timestamp('sent_for_signature_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()
                  ->constrained('partner_legal_documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['agreement_id', 'kind', 'status']);
            $table->index(['partner_firebase_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_legal_documents');
    }
};
