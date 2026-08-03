<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('category', 40)->default('opd')->after('service_type');
            $table->index(['hospital_id', 'category', 'status'], 'services_category_status_index');
        });

        // Backfill existing consultation-type rows as consultant fee / OPD catalog defaults.
        DB::table('services')
            ->where('code', 'OPDCONSULT')
            ->update(['category' => 'consultant_fee']);

        DB::table('services')
            ->whereNull('category')
            ->orWhere('category', '')
            ->update(['category' => 'opd']);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_category_status_index');
            $table->dropColumn('category');
        });
    }
};
