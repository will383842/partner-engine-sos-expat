<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds legal-gating fields to agreements.
 *
 * legal_status is the single source of truth for whether a partner has signed
 * all required documents. The AgreementObserver / Filament UI use it to block
 * sos_call_active=true until legal_status='signed'.
 *
 * 'override' is a manual escape hatch (audit-logged) for exceptional cases
 * (e.g. paper contract signed offline) where admin certifies legal compliance.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('legal_status', 32)->default('not_generated')
                  ->after('sos_call_active');
            //  not_generated | draft | pending_admin_validation | ready_for_signature
            //  | partially_signed | signed | superseded | override
            $table->timestamp('legal_signed_at')->nullable()->after('legal_status');
            $table->boolean('legal_override')->default(false)->after('legal_signed_at');
            $table->text('legal_override_reason')->nullable()->after('legal_override');
            $table->string('legal_override_by')->nullable()->after('legal_override_reason');
            $table->string('partner_legal_language', 8)->default('fr')
                  ->after('legal_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'legal_status',
                'legal_signed_at',
                'legal_override',
                'legal_override_reason',
                'legal_override_by',
                'partner_legal_language',
            ]);
        });
    }
};
