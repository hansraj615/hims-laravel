<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('ward_type', 40)->default('general');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'branch_id', 'status'], 'wards_tenant_status_index');
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('bed_number', 40);
            $table->string('bed_type', 40)->default('general');
            $table->string('status', 30)->default('available')->index();
            $table->unsignedBigInteger('current_admission_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['ward_id', 'bed_number']);
            $table->index(['hospital_id', 'branch_id', 'status'], 'beds_tenant_status_index');
        });

        Schema::create('admissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('admission_number', 50);
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('admitting_doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();
            $table->foreignId('bed_id')->constrained('beds')->restrictOnDelete();
            $table->timestamp('admitted_at');
            $table->string('provisional_diagnosis')->nullable();
            $table->string('attendant_name')->nullable();
            $table->string('attendant_mobile', 30)->nullable();
            $table->string('attendant_relation', 60)->nullable();
            $table->string('status', 40)->default('admitted')->index();
            $table->string('discharge_outcome', 40)->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->json('discharge_package')->nullable();
            $table->timestamp('death_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('discharge_document_id')->nullable()->constrained('patient_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'admission_number']);
            $table->index(['hospital_id', 'branch_id', 'status'], 'admissions_worklist_index');
            $table->index(['hospital_id', 'patient_id', 'admitted_at'], 'admissions_patient_index');
        });

        Schema::table('beds', function (Blueprint $table): void {
            $table->foreign('current_admission_id')->references('id')->on('admissions')->nullOnDelete();
        });

        Schema::create('bed_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('from_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('to_bed_id')->constrained('beds')->restrictOnDelete();
            $table->foreignId('from_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('to_ward_id')->constrained('wards')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index(['hospital_id', 'admission_id', 'transferred_at'], 'bed_transfers_admission_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_transfers');
        Schema::table('beds', function (Blueprint $table): void {
            $table->dropForeign(['current_admission_id']);
        });
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
