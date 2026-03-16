<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128);
            $table->string('month', 7); // "2026-03"

            $table->integer('total_subscribers')->default(0);
            $table->integer('new_subscribers')->default(0);
            $table->integer('active_subscribers')->default(0);
            $table->integer('total_calls')->default(0);
            $table->bigInteger('total_revenue_cents')->default(0);
            $table->bigInteger('total_commissions_cents')->default(0);
            $table->bigInteger('total_discounts_cents')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(['partner_firebase_id', 'month'], 'uq_monthly_stats_partner_month');
            $table->index('partner_firebase_id', 'idx_monthly_stats_partner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_monthly_stats');
    }
};
