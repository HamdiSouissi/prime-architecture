<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name_fr');
            $table->string('name_ar');
        });
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->restrictOnDelete();
            $table->string('key', 100)->unique();
            $table->string('name_fr');
            $table->string('name_ar');
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name')->index();
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 32)->nullable()->index();
            $table->string('national_id', 8)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 16)->nullable()->index();
            $table->string('whatsapp_phone', 16)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 16)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            // NULL values are not considered equal by a MySQL unique index.
            $table->unsignedBigInteger('active_primary_client_id')->nullable()
                ->storedAs('CASE WHEN is_primary = 1 AND deleted_at IS NULL THEN client_id ELSE NULL END')->unique();
        });
        Schema::create('project_reference_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name')->index();
            $table->string('project_type', 40)->index();
            $table->string('status', 40)->index();
            $table->foreignId('lead_architect_id')->constrained('users')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('land_title_number')->nullable();
            $table->decimal('land_area_m2', 12, 2)->nullable();
            $table->decimal('built_area_m2', 12, 2)->nullable();
            $table->unsignedSmallInteger('floors_count')->nullable();
            $table->decimal('estimated_budget', 16, 3)->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('expected_end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('project_role', 20);
            $table->timestamps();
            $table->primary(['project_id', 'user_id']);
            $table->unsignedBigInteger('lead_project_id')->nullable()
                ->storedAs("CASE WHEN project_role = 'lead' THEN project_id ELSE NULL END")->unique();
        });
        Schema::create('project_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['project_notes', 'project_status_history', 'project_user', 'projects', 'project_reference_counters', 'client_contacts', 'clients', 'delegations', 'governorates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
