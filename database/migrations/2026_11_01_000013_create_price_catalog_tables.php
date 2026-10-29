<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 120);
            $table->string('name', 200);
            $table->string('unit', 40);
            $table->decimal('unit_price', 15, 3);
            $table->timestamps();
        });
        Schema::create('price_item_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('unit_price', 15, 3);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('price_items')->exists()) {
            throw new \RuntimeException('Cannot roll back a non-empty price catalog.');
        }
        Schema::dropIfExists('price_item_prices');
        Schema::dropIfExists('price_items');
    }
};
