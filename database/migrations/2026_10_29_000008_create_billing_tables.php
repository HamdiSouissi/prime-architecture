<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('seed_key', 80)->nullable()->unique();
            $table->date('effective_from')->index();
            $table->string('currency', 3);
            $table->unsignedTinyInteger('currency_decimals');
            $table->decimal('stamp_amount', 15, 3);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_setting_id')->constrained('tax_settings')->restrictOnDelete();
            $table->string('label_fr');
            $table->string('label_ar');
            $table->decimal('rate', 7, 4);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::create('withholding_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_setting_id')->constrained('tax_settings')->restrictOnDelete();
            $table->string('label_fr');
            $table->string('label_ar');
            $table->decimal('rate', 7, 4);
            $table->string('base', 8);
            $table->boolean('includes_stamp')->default(false);
            $table->timestamps();
        });
        Schema::create('billing_number_formats', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20);
            $table->date('effective_from');
            $table->string('format', 100);
            $table->unsignedTinyInteger('padding');
            $table->timestamps();
            $table->unique(['document_type', 'effective_from']);
        });
        Schema::create('billing_number_counters', function (Blueprint $table) {
            $table->string('document_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['document_type', 'year']);
        });
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->index();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('legal_terms_fr')->nullable();
            $table->text('legal_terms_ar')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 100)->nullable()->unique();
            $table->string('status', 20)->index();
            $table->date('document_date')->index();
            $table->date('valid_until')->nullable()->index();
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('total_ht', 15, 3)->default(0);
            $table->decimal('total_discount', 15, 3)->default(0);
            $table->decimal('total_tax', 15, 3)->default(0);
            $table->decimal('stamp_amount', 15, 3)->default(0);
            $table->decimal('withholding_amount', 15, 3)->default(0);
            $table->decimal('total_ttc', 15, 3)->default(0);
            $table->decimal('net_due', 15, 3)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index(['client_id', 'document_date']);
        });
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 15, 6);
            $table->decimal('unit_price', 15, 3);
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('tax_rate', 7, 4);
            $table->decimal('total_ht', 15, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 100)->nullable()->unique();
            $table->string('status', 24)->index();
            $table->date('document_date')->index();
            $table->date('due_date')->index();
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('total_ht', 15, 3)->default(0);
            $table->decimal('total_discount', 15, 3)->default(0);
            $table->decimal('total_tax', 15, 3)->default(0);
            $table->decimal('stamp_amount', 15, 3)->default(0);
            $table->decimal('withholding_amount', 15, 3)->default(0);
            $table->decimal('total_ttc', 15, 3)->default(0);
            $table->decimal('net_due', 15, 3)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['client_id', 'document_date']);
        });
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 15, 6);
            $table->decimal('unit_price', 15, 3);
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('tax_rate', 7, 4);
            $table->decimal('total_ht', 15, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 3);
            $table->date('payment_date')->index();
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 3);
            $table->date('recorded_date');
            $table->string('certificate_reference');
            $table->timestamps();
        });
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 100)->unique();
            $table->date('document_date')->index();
            $table->text('reason');
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('phase', 40);
            $table->string('mode', 20);
            $table->decimal('value', 15, 6);
            $table->decimal('works_amount', 15, 3)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'phase']);
        });
    }

    public function down(): void
    {
        foreach (['fee_schedules', 'credit_notes', 'payment_withholdings', 'payments', 'invoice_lines', 'invoices', 'quote_lines', 'quotes', 'billing_profiles', 'billing_number_counters', 'billing_number_formats', 'withholding_rates', 'tax_rates', 'tax_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
