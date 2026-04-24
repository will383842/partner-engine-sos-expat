<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SOS-Call fields to subscribers.
 *
 * - sos_call_code: unique code format "PREFIX-YEAR-RANDOM5" (e.g. XXX-2026-A3K9M)
 * - activated_at / expires_at: lifecycle of SOS-Call access
 * - calls_expert / calls_lawyer: per-type call counters
 *
 * All fields nullable to preserve backward compatibility with existing subscribers.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('sos_call_code', 20)->nullable()->unique()->after('affiliate_code');
            $table->timestamp('sos_call_activated_at')->nullable()->after('sos_call_code');
            $table->timestamp('sos_call_expires_at')->nullable()->after('sos_call_activated_at');
            $table->unsignedInteger('calls_expert')->default(0)->after('sos_call_expires_at');
            $table->unsignedInteger('calls_lawyer')->default(0)->after('calls_expert');

            // Composite index for fast lookup during /sos-call/check by phone+email fallback
            $table->index(['phone', 'email', 'status'], 'idx_subscribers_sos_call_lookup');

            // Index for quick lookup by code (already unique, but named for clarity)
            $table->index('sos_call_code', 'idx_subscribers_sos_call_code_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex('idx_subscribers_sos_call_lookup');
            $table->dropIndex('idx_subscribers_sos_call_code_lookup');
            $table->dropUnique(['sos_call_code']);
            $table->dropColumn([
                'sos_call_code',
                'sos_call_activated_at',
                'sos_call_expires_at',
                'calls_expert',
                'calls_lawyer',
            ]);
        });
    }
};
