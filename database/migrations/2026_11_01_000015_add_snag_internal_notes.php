<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snags', function (Blueprint $table): void {
            $table->text('internal_notes')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('snags')->whereNotNull('internal_notes')->exists()) {
            throw new RuntimeException('Cannot discard internal snag notes.');
        }
        Schema::table('snags', function (Blueprint $table): void {
            $table->dropColumn('internal_notes');
        });
    }
};
