<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->string('code', 100);
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['hospital_id', 'code', 'channel'], 'notification_templates_lookup_index');
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('template_code', 100);
            $table->string('channel', 20);
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider', 60)->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'user_id', 'created_at'], 'notification_logs_user_index');
            $table->index(['related_type', 'related_id'], 'notification_logs_related_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
