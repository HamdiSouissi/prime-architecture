<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_schedules', function (Blueprint $table) {
            $table->decimal('value', 18, 6)->change();
        });
    }

    public function down(): void
    {
        // Refuse to truncate data when returning to the old, narrower column.
        if (DB::table('fee_schedules')->where('value', '>', '999999999.999999')->exists()) {
            throw new RuntimeException('Fee values exceed the previous column precision; correct them before rolling back.');
        }
        Schema::table('fee_schedules', function (Blueprint $table) {
            $table->decimal('value', 15, 6)->change();
        });
    }
};
