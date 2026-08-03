<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile', 20)->nullable()->unique()->after('email');
            $table->string('status', 20)->default('active')->after('password')->index();
            $table->timestamp('locked_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mobile',
                'status',
                'locked_at',
                'last_login_at',
            ]);
        });
    }
};
