<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SOS-Call (B2B monthly subscription model) fields to agreements.
 *
 * These fields are all nullable with safe defaults so existing agreements
 * continue to operate under the legacy commission-per-call model (system A).
 * SOS-Call only activates when sos_call_active is manually set to true by
 * an admin via Filament or the SPA admin UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // Billing config (system B — SOS-Call monthly flat-rate)
            $table->decimal('billing_rate', 8, 2)->default(3.00)->after('commission_percent');
            $table->string('billing_currency', 3)->default('EUR')->after('billing_rate');
            $table->unsignedTinyInteger('payment_terms_days')->default(15)->after('billing_currency');
            $table->string('call_types_allowed', 20)->default('both')->after('payment_terms_days'); // both|expat_only|lawyer_only
            $table->boolean('sos_call_active')->default(false)->after('call_types_allowed'); // ⚠️ FALSE by default
            $table->string('billing_email', 255)->nullable()->after('sos_call_active');

            // Subscriber duration policy (per contract)
            $table->unsignedSmallInteger('default_subscriber_duration_days')->nullable()->after('billing_email');
            $table->unsignedSmallInteger('max_subscriber_duration_days')->nullable()->after('default_subscriber_duration_days');

            // Index to quickly find agreements with SOS-Call active (used by GenerateMonthlyInvoices)
            $table->index(['sos_call_active', 'status'], 'idx_agreements_sos_call_active_status');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropIndex('idx_agreements_sos_call_active_status');
            $table->dropColumn([
                'billing_rate',
                'billing_currency',
                'payment_terms_days',
                'call_types_allowed',
                'sos_call_active',
                'billing_email',
                'default_subscriber_duration_days',
                'max_subscriber_duration_days',
            ]);
        });
    }
};
