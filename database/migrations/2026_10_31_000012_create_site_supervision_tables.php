<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->date('meeting_date')->index();
            $table->string('status', 20)->index()->default('draft');
            $table->text('agenda')->nullable();
            $table->text('decisions')->nullable();
            $table->date('next_meeting_on')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'meeting_date']);
        });

        Schema::create('site_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('recorded_on')->index();
            $table->decimal('percent', 5, 2);
            // Snapshot of the planning-derived percentage, kept for comparison.
            $table->decimal('computed_percent', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'recorded_on']);
        });

        Schema::create('snags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_report_id')->nullable()->constrained('site_reports')->nullOnDelete();
            $table->string('reference', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('responsible', 150)->nullable();
            $table->string('severity', 20)->index()->default('minor');
            $table->string('status', 20)->index()->default('open');
            $table->date('due_on')->nullable()->index();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'reference']);
            $table->index(['project_id', 'status', 'due_on']);
        });

        Schema::create('site_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_report_id')->nullable()->constrained('site_reports')->nullOnDelete();
            $table->foreignId('snag_id')->nullable()->constrained('snags')->nullOnDelete();
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('original_name', 200);
            $table->string('mime_type', 60);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64);
            $table->string('caption', 200)->nullable();
            $table->date('taken_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
            $table->index(['site_report_id', 'id']);
            $table->index(['snag_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_photos');
        Schema::dropIfExists('snags');
        Schema::dropIfExists('site_progress_updates');
        Schema::dropIfExists('site_reports');
    }
};
