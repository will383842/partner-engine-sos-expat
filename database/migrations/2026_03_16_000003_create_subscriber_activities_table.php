<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('subscribers')->cascadeOnDelete();
            $table->string('partner_firebase_id', 128);

            $table->string('type', 50); // call_completed|registered|invitation_sent|invitation_clicked|discount_applied

            // Call-specific data
            $table->string('call_session_id', 128)->nullable();
            $table->string('provider_type', 20)->nullable(); // lawyer|expat
            $table->integer('call_duration_seconds')->nullable();
            $table->integer('amount_paid_cents')->nullable();
            $table->integer('discount_applied_cents')->nullable();
            $table->integer('commission_earned_cents')->nullable();

            $table->jsonb('metadata')->default('{}');

            $table->timestamp('created_at')->useCurrent();

            $table->index('subscriber_id', 'idx_activities_subscriber');
            $table->index('partner_firebase_id', 'idx_activities_partner');
            $table->index('created_at', 'idx_activities_created');
            $table->index('call_session_id', 'idx_activities_call_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_activities');
    }
};
