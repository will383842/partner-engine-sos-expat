<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates partner_invoices table for SOS-Call monthly B2B invoicing.
 *
 * - One invoice per (agreement_id, period YYYY-MM)
 * - total_amount = active_subscribers × billing_rate
 * - total_cost (internal cost) = (calls_expert × 10) + (calls_lawyer × 30) — NOT re-billed
 * - Stripe integration: stripe_invoice_id + stripe_hosted_url for online payment
 * - Status lifecycle: pending → paid | overdue | cancelled
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_invoices', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->string('partner_firebase_id', 128)->index();

            // Invoice identity
            $table->string('invoice_number', 30)->unique(); // Format: SOS-202604-0001
            $table->string('period', 7); // YYYY-MM

            // Billing snapshot (values at generation time, not live config)
            $table->unsignedInteger('active_subscribers');
            $table->decimal('billing_rate', 8, 2);
            $table->string('billing_currency', 3);
            $table->decimal('total_amount', 10, 2);

            // Usage metrics (informational, not re-billed)
            $table->unsignedInteger('calls_expert')->default(0);
            $table->unsignedInteger('calls_lawyer')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0); // Internal SOS-Expat cost

            // Payment lifecycle
            $table->string('status', 20)->default('pending'); // pending | paid | overdue | cancelled
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_via', 30)->nullable(); // stripe | sepa_transfer | manual
            $table->text('payment_note')->nullable();

            // Stripe Invoicing integration
            $table->string('stripe_customer_id', 64)->nullable();
            $table->string('stripe_invoice_id', 64)->nullable()->unique();
            $table->text('stripe_hosted_url')->nullable();

            // Storage
            $table->string('pdf_path', 512)->nullable();

            $table->timestamps();

            // Unique constraint: one invoice per agreement+period
            $table->unique(['agreement_id', 'period'], 'uq_partner_invoices_agreement_period');

            // Indexes for admin queries
            $table->index(['status', 'due_date'], 'idx_partner_invoices_status_due');
            $table->index(['partner_firebase_id', 'period'], 'idx_partner_invoices_partner_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_invoices');
    }
};
