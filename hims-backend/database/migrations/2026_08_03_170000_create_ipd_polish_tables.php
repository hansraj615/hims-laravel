<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipd_nursing_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->json('vitals')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'admission_id', 'recorded_at'], 'ipd_nursing_notes_admission_index');
        });

        Schema::create('ipd_charge_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->date('charge_date');
            $table->string('description');
            $table->string('source', 40)->default('manual');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['admission_id', 'source', 'charge_date', 'service_id'], 'ipd_charge_lines_unique_auto');
            $table->index(['hospital_id', 'admission_id', 'status'], 'ipd_charge_lines_worklist_index');
        });

        Schema::create('admission_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->string('clearance_type', 40);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['admission_id', 'clearance_type']);
            $table->index(['hospital_id', 'status'], 'admission_clearances_hospital_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_clearances');
        Schema::dropIfExists('ipd_charge_lines');
        Schema::dropIfExists('ipd_nursing_notes');
    }
};
