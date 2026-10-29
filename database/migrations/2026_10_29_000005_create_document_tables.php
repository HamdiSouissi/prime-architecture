<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name_fr');
            $table->string('name_ar');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('document_categories')->restrictOnDelete();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('visible_to_client')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'category_id']);
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 50);
            $table->string('path', 255);
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->text('change_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['document_id', 'version_number']);
            $table->unique(['disk', 'path']);
            $table->unique(['document_id', 'id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign(['id', 'current_version_id'], 'documents_current_version_same_document_fk')->references(['document_id', 'id'])->on('document_versions')->restrictOnDelete();
        });
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_version_id')->nullable();
            $table->foreign(['document_id', 'document_version_id'], 'document_comments_version_same_document_fk')->references(['document_id', 'id'])->on('document_versions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
        // The committed intent exists before a blob is written. Never remove a blob
        // on an uncertain commit; an operator can reconcile stale intents.
        Schema::create('document_upload_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk', 50);
            $table->string('path', 255);
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_upload_intents');
        Schema::dropIfExists('document_comments');
        Schema::table('documents', fn (Blueprint $table) => $table->dropForeign('documents_current_version_same_document_fk'));
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
