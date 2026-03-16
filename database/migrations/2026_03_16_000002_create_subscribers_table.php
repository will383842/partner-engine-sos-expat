<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128);
            $table->foreignId('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();

            // Identity
            $table->string('email', 255);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('language', 5)->default('fr');

            // SOS-Expat link
            $table->string('firebase_uid', 128)->nullable();
            $table->string('affiliate_code', 50)->nullable();
            $table->string('invite_token', 64)->unique();

            // Status
            $table->string('status', 20)->default('invited'); // invited|registered|active|suspended|expired
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            // Activity counters
            $table->integer('total_calls')->default(0);
            $table->bigInteger('total_spent_cents')->default(0);
            $table->bigInteger('total_discount_cents')->default(0);

            // Metadata
            $table->jsonb('tags')->default('[]');
            $table->jsonb('custom_fields')->default('{}');

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: one email per partner
            $table->unique(['partner_firebase_id', 'email'], 'uq_subscribers_partner_email');

            $table->index('partner_firebase_id', 'idx_subscribers_partner');
            $table->index('email', 'idx_subscribers_email');
            $table->index('firebase_uid', 'idx_subscribers_firebase_uid');
            $table->index('status', 'idx_subscribers_status');
            $table->index('invite_token', 'idx_subscribers_invite_token');
        });

        // Partial index for soft deletes (PostgreSQL-specific)
        DB::statement('CREATE INDEX idx_subscribers_deleted ON subscribers(deleted_at) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
