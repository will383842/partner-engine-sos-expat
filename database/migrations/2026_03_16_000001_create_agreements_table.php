<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128);
            $table->string('partner_name', 255)->nullable();
            $table->string('name', 255);
            $table->string('status', 20)->default('draft'); // draft|active|paused|expired

            // Subscriber discounts
            $table->string('discount_type', 20)->default('none'); // none|fixed|percent
            $table->integer('discount_value')->default(0);
            $table->integer('discount_max_cents')->nullable();
            $table->string('discount_label', 255)->nullable();

            // Partner commissions
            $table->integer('commission_per_call_lawyer')->default(500);
            $table->integer('commission_per_call_expat')->default(300);
            $table->string('commission_type', 20)->default('fixed'); // fixed|percent
            $table->decimal('commission_percent', 5, 2)->nullable();

            // Limits
            $table->integer('max_subscribers')->nullable();
            $table->integer('max_calls_per_subscriber')->nullable();

            // Duration
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('partner_firebase_id', 'idx_agreements_partner');
        });

        // Partial indexes (PostgreSQL-specific, not supported by Blueprint)
        DB::statement('CREATE INDEX idx_agreements_expires ON agreements(expires_at) WHERE status = \'active\' AND expires_at IS NOT NULL');
        DB::statement('CREATE INDEX idx_agreements_deleted ON agreements(deleted_at) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
