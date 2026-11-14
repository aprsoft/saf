<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained('product_services')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->decimal('precio', 18, 2);
            $table->timestamps();
            $table->unique(['product_service_id', 'unit_id', 'price_list_id'], 'product_prices_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('product_prices'); }
};
