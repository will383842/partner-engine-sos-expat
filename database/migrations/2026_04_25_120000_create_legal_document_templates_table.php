<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legal_document_templates — versioned global templates for B2B legal documents.
 *
 * One row per (kind, language, version). The "active" version of each (kind, language)
 * pair is the one whose published_at is the most recent and not superseded.
 *
 * Kinds:
 *   - 'cgv_b2b'    Conditions Générales de Vente B2B (Terms of Service)
 *   - 'dpa'        Data Processing Agreement (RGPD article 28)
 *   - 'order_form' Bon de commande (per-partner; this row holds the boilerplate
 *                  that wraps the agreement-specific clauses)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);                  // cgv_b2b | dpa | order_form
            $table->string('language', 8);               // fr, en, es, de, pt, ar, zh, ru, hi
            $table->string('version', 16);               // semver: 1.0.0
            $table->string('title');                     // "Conditions Générales de Vente B2B"
            $table->longText('body_html');               // Blade-compatible content with @{{ vars }}
            $table->json('variables')->nullable();       // declared vars: ["partner_name","billing_rate",...]
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('published_by')->nullable();  // admin firebase_id or "admin:filament"
            $table->text('change_notes')->nullable();    // changelog entry
            $table->timestamps();

            $table->unique(['kind', 'language', 'version']);
            $table->index(['kind', 'language', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_templates');
    }
};
