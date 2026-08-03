<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_queues', function (Blueprint $table) {
            $table->json('vitals')->nullable()->after('status');
            $table->timestamp('vitals_recorded_at')->nullable()->after('vitals');
            $table->foreignId('vitals_recorded_by')->nullable()->after('vitals_recorded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opd_queues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vitals_recorded_by');
            $table->dropColumn(['vitals', 'vitals_recorded_at']);
        });
    }
};
