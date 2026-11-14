<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained('product_services')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor_conversion', 18, 6);
            $table->timestamps();
            $table->unique(['product_service_id', 'unit_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('product_units'); }
};
