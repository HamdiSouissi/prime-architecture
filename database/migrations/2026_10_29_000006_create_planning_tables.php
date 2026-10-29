<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->string('status', 20)->index();
            $table->string('priority', 20)->index();
            $table->string('project_phase', 40)->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->decimal('estimated_hours', 9, 2)->nullable();
            $table->decimal('spent_hours', 9, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('visible_to_client')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index('parent_id');
            $table->unique(['project_id', 'id']);
            $table->foreign(['project_id', 'parent_id'], 'tasks_parent_same_project_fk')->references(['project_id', 'id'])->on('tasks')->restrictOnDelete();
        });
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['task_id', 'user_id']);
            $table->foreign(['project_id', 'task_id'], 'task_assignees_task_project_fk')->references(['project_id', 'id'])->on('tasks')->restrictOnDelete();
            $table->foreign(['project_id', 'user_id'], 'task_assignees_project_member_fk')->references(['project_id', 'user_id'])->on('project_user')->restrictOnDelete();
            $table->index(['user_id', 'task_id']);
        });
        Schema::create('task_followers', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['task_id', 'user_id']);
            $table->foreign(['project_id', 'task_id'], 'task_followers_task_project_fk')->references(['project_id', 'id'])->on('tasks')->restrictOnDelete();
            $table->foreign(['project_id', 'user_id'], 'task_followers_project_member_fk')->references(['project_id', 'user_id'])->on('project_user')->restrictOnDelete();
        });
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('depends_on_task_id');
            $table->primary(['task_id', 'depends_on_task_id']);
            $table->foreign(['project_id', 'task_id'], 'task_dependencies_task_project_fk')->references(['project_id', 'id'])->on('tasks')->restrictOnDelete();
            $table->foreign(['project_id', 'depends_on_task_id'], 'task_dependencies_predecessor_project_fk')->references(['project_id', 'id'])->on('tasks')->restrictOnDelete();
        });
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('due_date')->index();
            $table->boolean('is_reached')->default(false);
            $table->timestamp('reached_at')->nullable();
            $table->string('project_phase', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'due_date']);
        });
        Schema::create('planning_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_fr');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('planning_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_template_id')->constrained()->restrictOnDelete();
            $table->string('title_fr');
            $table->string('title_ar');
            $table->string('type', 20);
            $table->unsignedInteger('offset_workdays')->default(0);
            $table->unsignedInteger('duration_workdays')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('project_phase', 40)->nullable();
            $table->boolean('visible_to_client')->default(false);
            $table->timestamps();
            $table->unique(['planning_template_id', 'id']);
        });
        Schema::create('planning_template_dependencies', function (Blueprint $table) {
            $table->unsignedBigInteger('planning_template_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('depends_on_item_id');
            $table->primary(['item_id', 'depends_on_item_id']);
            $table->foreign(['planning_template_id', 'item_id'], 'template_deps_item_same_template_fk')->references(['planning_template_id', 'id'])->on('planning_template_items')->restrictOnDelete();
            $table->foreign(['planning_template_id', 'depends_on_item_id'], 'template_deps_pred_same_template_fk')->references(['planning_template_id', 'id'])->on('planning_template_items')->restrictOnDelete();
        });
        Schema::create('planning_calendar_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        \Illuminate\Support\Facades\DB::table('planning_calendar_state')->insert(['id' => 1, 'revision' => 0]);
        Schema::create('working_calendar_days', function (Blueprint $table) {
            $table->unsignedTinyInteger('iso_weekday')->primary();
            $table->boolean('is_working');
            $table->timestamps();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->nullable()->unique();
            $table->date('date')->index();
            $table->string('name_fr');
            $table->string('name_ar');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('planning_email_enabled')->default(true);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('planning_daily_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('digest_date');
            $table->string('status', 20);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'digest_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_daily_digests');
        Schema::dropIfExists('notifications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('planning_email_enabled'));
        foreach (['holidays', 'working_calendar_days', 'planning_calendar_state', 'planning_template_dependencies', 'planning_template_items', 'planning_templates', 'milestones', 'task_comments', 'task_dependencies', 'task_followers', 'task_assignees'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('tasks', fn (Blueprint $table) => $table->dropForeign('tasks_parent_same_project_fk'));
        Schema::dropIfExists('tasks');
    }
};
