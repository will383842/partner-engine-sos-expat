<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add fields for email-based 2FA on the admin users table.
 *
 * When `two_factor_email_enabled` is true, every login attempt is gated
 * behind a 6-digit code sent by email (10-min TTL). Implemented as a
 * dedicated middleware so we don't depend on Laravel Fortify / Jetstream.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_email_enabled')->default(false)->after('two_factor_confirmed_at');
            $table->string('two_factor_email_code', 16)->nullable()->after('two_factor_email_enabled');
            $table->timestamp('two_factor_email_code_expires_at')->nullable()->after('two_factor_email_code');
            $table->timestamp('two_factor_email_verified_at')->nullable()->after('two_factor_email_code_expires_at');
            $table->unsignedTinyInteger('two_factor_email_attempts')->default(0)->after('two_factor_email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_email_enabled',
                'two_factor_email_code',
                'two_factor_email_code_expires_at',
                'two_factor_email_verified_at',
                'two_factor_email_attempts',
            ]);
        });
    }
};
