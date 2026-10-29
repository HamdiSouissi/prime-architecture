<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('measurement_lines')->exists()) {
            throw new RuntimeException('Cannot roll back non-empty measurements.');
        }
        Schema::dropIfExists('measurement_lines');
    }
};
