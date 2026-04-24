<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds language support to email_templates for multilingual notifications.
 *
 * Partners can now have different email templates per language (9 languages).
 * Also allows global default templates (partner_firebase_id = NULL) per language.
 *
 * New unique constraint: (partner_firebase_id, type, language)
 * Replaces old unique: (partner_firebase_id, type)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            // Drop existing unique constraint
            $table->dropUnique('uq_email_templates_partner_type');

            // Add language column (fr, en, es, de, pt, ar, zh, ru, hi)
            $table->string('language', 5)->default('fr')->after('type');

            // New unique constraint including language
            $table->unique(['partner_firebase_id', 'type', 'language'], 'uq_email_templates_partner_type_language');

            // Index for fast lookup by type+language (global templates)
            $table->index(['type', 'language'], 'idx_email_templates_type_language');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropIndex('idx_email_templates_type_language');
            $table->dropUnique('uq_email_templates_partner_type_language');
            $table->dropColumn('language');

            // Restore old unique
            $table->unique(['partner_firebase_id', 'type'], 'uq_email_templates_partner_type');
        });
    }
};
