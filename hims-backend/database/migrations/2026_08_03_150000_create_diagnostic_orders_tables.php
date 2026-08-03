<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('category', 40);
            $table->string('priority', 20)->default('routine');
            $table->string('status', 40)->default('ordered')->index();
            $table->text('clinical_notes')->nullable();
            $table->text('result_summary')->nullable();
            $table->json('result_payload')->nullable();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('resulted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resulted_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('patient_document_id')->nullable()->constrained('patient_documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'order_number']);
            $table->index(['hospital_id', 'branch_id', 'category', 'status'], 'diagnostic_orders_worklist_index');
            $table->index(['hospital_id', 'patient_id', 'ordered_at'], 'diagnostic_orders_patient_index');
        });

        Schema::create('diagnostic_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagnostic_order_id')->constrained('diagnostic_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_code', 50)->nullable();
            $table->string('service_name');
            $table->string('category', 40);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->string('status', 40)->default('ordered');
            $table->timestamps();

            $table->index(['diagnostic_order_id', 'status'], 'diagnostic_order_items_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_order_items');
        Schema::dropIfExists('diagnostic_orders');
    }
};
