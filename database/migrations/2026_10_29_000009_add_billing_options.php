<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->boolean('stamp_on_quote')->default(false);
            $table->boolean('stamp_on_invoice')->default(false);
        });
        foreach (['quotes', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('withholding_rate_id')->nullable()->constrained('withholding_rates')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'quotes'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('withholding_rate_id');
            });
        }
        Schema::table('tax_settings', fn (Blueprint $table) => $table->dropColumn(['stamp_on_quote', 'stamp_on_invoice']));
    }
};
