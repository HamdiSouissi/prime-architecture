<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_id');
            $table->string('payload_hash', 64);
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_progress_update_id')->constrained('site_progress_updates')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('offline_operations')->exists()) {
            throw new RuntimeException('Cannot discard offline synchronization history.');
        }
        Schema::dropIfExists('offline_operations');
    }
};
