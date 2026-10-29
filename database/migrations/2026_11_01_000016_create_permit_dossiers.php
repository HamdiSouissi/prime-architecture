<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->string('permit_type', 120);
            $table->string('municipality', 150)->nullable();
            $table->string('status', 120);
            $table->date('submitted_on')->nullable();
            $table->date('due_on')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('permit_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_dossier_id')->constrained()->restrictOnDelete();
            $table->string('label', 200);
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('permit_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unique(['permit_dossier_id', 'document_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('permit_dossiers')->exists()) {
            throw new RuntimeException('Cannot discard permit dossiers.');
        }
        Schema::dropIfExists('permit_documents');
        Schema::dropIfExists('permit_steps');
        Schema::dropIfExists('permit_dossiers');
    }
};
