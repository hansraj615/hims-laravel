<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('service_type', 50);
            $table->string('hsn_sac_code', 20)->nullable();
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'service_type', 'status'], 'services_type_status_index');
        });

        Schema::create('cashier_daybooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->string('status', 30)->default('open')->index();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('cash_collected', 12, 2)->default(0);
            $table->decimal('cash_refunded', 12, 2)->default(0);
            $table->decimal('closing_cash', 12, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'branch_id', 'cashier_user_id', 'business_date'], 'cashier_daybook_unique');
            $table->index(['hospital_id', 'branch_id', 'business_date', 'status'], 'cashier_daybook_status_index');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('cashier_daybook_id')->nullable()->constrained('cashier_daybooks')->nullOnDelete();
            $table->string('invoice_number', 50);
            $table->string('invoice_type', 40)->default('opd');
            $table->string('payer_type', 40)->default('self');
            $table->string('scheme_type', 80)->nullable();
            $table->string('tpa_name')->nullable();
            $table->string('claim_reference')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('taxable_total', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('igst_total', 12, 2)->default(0);
            $table->decimal('round_off', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('balance_total', 12, 2)->default(0);
            $table->timestamp('billed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'invoice_number']);
            $table->index(['hospital_id', 'branch_id', 'status', 'billed_at'], 'invoices_worklist_index');
            $table->index(['hospital_id', 'patient_id', 'created_at'], 'invoices_patient_index');
            $table->index(['hospital_id', 'payer_type', 'scheme_type'], 'invoices_payer_index');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('billable_type')->nullable();
            $table->unsignedBigInteger('billable_id')->nullable();
            $table->string('description');
            $table->string('hsn_sac_code', 20)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id'], 'invoice_items_billable_index');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('cashier_daybook_id')->nullable()->constrained('cashier_daybooks')->nullOnDelete();
            $table->string('receipt_number', 50);
            $table->string('payment_type', 40)->default('receipt');
            $table->string('payment_mode', 40);
            $table->decimal('amount', 12, 2);
            $table->string('status', 40)->default('posted')->index();
            $table->string('reference_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'receipt_number']);
            $table->index(['hospital_id', 'branch_id', 'paid_at', 'payment_mode'], 'payments_daybook_index');
            $table->index(['hospital_id', 'patient_id', 'paid_at'], 'payments_patient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cashier_daybooks');
        Schema::dropIfExists('services');
    }
};
