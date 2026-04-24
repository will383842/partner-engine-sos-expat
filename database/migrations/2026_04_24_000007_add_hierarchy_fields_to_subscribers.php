<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchy support for subscribers.
 *
 * Use cases:
 *   - A large insurer with 300+ cabinets and wants separate tracking per cabinet
 *   - A retail bank with regions (Île-de-France, Rhône-Alpes…)
 *   - A large employer has departments (IT, HR, Sales…)
 *
 * All 4 fields are free-text so the partner defines their own hierarchy.
 * Indexed for fast filter/group-by queries.
 *
 * Partners pay a single monthly invoice for all their subscribers (one
 * agreement, one billing). The hierarchy is purely for reporting.
 * For separately billed sub-entities, create separate agreements instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // Top-level grouping: "Paris", "Lyon", "Cabinet Nord", "Région IDF"
            $table->string('group_label', 120)->nullable()->after('agreement_id');

            // Geographic: "Île-de-France", "North America", "APAC"
            $table->string('region', 120)->nullable()->after('group_label');

            // Functional: "IT", "HR", "Sales", "Back-office"
            $table->string('department', 120)->nullable()->after('region');

            // Partner's own ID: allows reconciliation with partner CRM/HR
            $table->string('external_id', 255)->nullable()->after('department');

            // Composite index for fast group-by queries per partner
            $table->index(['partner_firebase_id', 'group_label'], 'idx_sub_partner_group');
            $table->index(['partner_firebase_id', 'region'], 'idx_sub_partner_region');
            $table->index(['partner_firebase_id', 'department'], 'idx_sub_partner_dept');
            $table->index(['partner_firebase_id', 'external_id'], 'idx_sub_partner_extid');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex('idx_sub_partner_group');
            $table->dropIndex('idx_sub_partner_region');
            $table->dropIndex('idx_sub_partner_dept');
            $table->dropIndex('idx_sub_partner_extid');
            $table->dropColumn(['group_label', 'region', 'department', 'external_id']);
        });
    }
};
