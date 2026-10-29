<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_reminder_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('reminder_date');
            $table->timestamps();
            $table->unique(['user_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        if (DB::table('permit_reminder_runs')->exists()) {
            throw new RuntimeException('Cannot discard permit reminder history.');
        }
        Schema::dropIfExists('permit_reminder_runs');
    }
};
