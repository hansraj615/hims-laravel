<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_session_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('login_method', 30);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_out_at']);
        });

        Schema::create('login_otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('mobile', 20)->index();
            $table->string('otp_hash');
            $table->string('purpose', 30)->default('login');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['mobile', 'purpose', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otp_challenges');
        Schema::dropIfExists('user_session_histories');
    }
};
