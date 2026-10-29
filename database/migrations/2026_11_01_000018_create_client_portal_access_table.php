<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('financial_access')->default(false);
            $table->timestamps();
            $table->unique(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('client_portal_access')->exists()) {
            throw new RuntimeException('Cannot discard client portal access grants.');
        }
        Schema::dropIfExists('client_portal_access');
    }
};
