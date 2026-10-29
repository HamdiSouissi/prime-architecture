<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Versioned cost settings, mirroring tax_settings: a new row replaces the
        // applicable version instead of mutating history used by past reports.
        Schema::create('profitability_settings', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->index();
            $table->string('currency', 3)->default('TND');
            $table->unsignedTinyInteger('currency_decimals')->default(3);
            $table->decimal('default_hourly_cost', 12, 3)->default(0);
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('user_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from')->index();
            $table->decimal('hourly_cost_rate', 12, 3);
            $table->timestamps();
            $table->unique(['user_id', 'effective_from']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('worked_on')->index();
            $table->unsignedSmallInteger('minutes');
            $table->text('note')->nullable();
            $table->boolean('billable')->default(true);
            // Cost is frozen when the entry is created so later rate changes do not
            // rewrite the profitability of already reported periods.
            $table->decimal('hourly_cost_rate', 12, 3)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'worked_on']);
            $table->index(['user_id', 'worked_on']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('user_cost_rates');
        Schema::dropIfExists('profitability_settings');
    }
};
