<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner API keys — for server-to-server integrations (automated subscriber
 * provisioning from the partner's own systems).
 *
 * Each partner can have 0..N API keys. Each key:
 *   - starts with `pk_live_` (prod) or `pk_test_` (sandbox) prefix
 *   - stored HASHED in the DB (bcrypt) — the plain value is shown only once
 *     at creation time, then never again (just like Stripe/GitHub API keys)
 *   - can be scoped (read-only, write-subscribers, etc.)
 *   - can be revoked at any time
 *   - tracks last use for audit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128)->index();
            $table->string('name', 100); // human-readable label, e.g. "Production CRM integration"

            // Plain-text prefix (8 chars) stored for display ("pk_live_X7k2P...")
            // so the partner can identify which key is which in the UI.
            $table->string('prefix', 16);

            // Bcrypt hash of the full secret. The secret value is shown ONCE at creation.
            $table->string('hashed_secret', 255);

            // Scopes: comma-separated list of permissions
            //   subscribers:read / subscribers:write / invoices:read / activity:read
            $table->string('scopes', 255)->default('subscribers:write,subscribers:read,activity:read');

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by', 128)->nullable(); // actor firebase id

            $table->timestamps();

            $table->index(['partner_firebase_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_api_keys');
    }
};
