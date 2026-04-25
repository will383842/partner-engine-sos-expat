<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of multi-cabinet partner architecture.
 *
 * Adds `managed_group_labels` (JSON, nullable) to users so a partner-company
 * account can be restricted to a subset of cabinets/branches.
 *
 * Semantics:
 *   - role='partner', managed_group_labels=NULL  → group admin, sees ALL cabinets (legacy default)
 *   - role='branch_manager', managed_group_labels=["Paris","Lyon"]  → restricted to those cabinets
 *   - role='branch_manager', managed_group_labels=NULL or []  → fail-closed, sees nothing
 *
 * The actual scoping is applied in PartnerScopedQuery — this migration only
 * provisions the storage. No existing accounts are affected (NULL by default).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('managed_group_labels')->nullable()->after('partner_firebase_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('managed_group_labels');
        });
    }
};
