<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->enum('tipo', ['PRODUCTO', 'SERVICIO']);
            $table->foreignId('stock_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('sales_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->boolean('controla_stock')->default(true);
            $table->boolean('afecto_iva')->default(true);
            $table->boolean('activo')->default(true);
            $table->text('obs')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('product_services'); }
};
