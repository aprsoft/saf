<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained('product_services')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('tipo', ['ENTRADA', 'SALIDA', 'AJUSTE']);
            $table->decimal('cantidad_base', 18, 6);
            $table->decimal('costo_unitario_base', 18, 4)->nullable();
            $table->string('referencia')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->index(['product_service_id','warehouse_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('inventory_movements'); }
};
