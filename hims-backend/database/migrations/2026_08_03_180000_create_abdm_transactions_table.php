<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abdm_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 60);
            $table->string('provider', 40)->default('simulated');
            $table->string('status', 30)->default('pending')->index();
            $table->string('external_txn_id')->nullable()->index();
            $table->string('abha_number', 30)->nullable();
            $table->string('abha_address')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'operation', 'status'], 'abdm_transactions_worklist_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abdm_transactions');
    }
};
