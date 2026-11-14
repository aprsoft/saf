<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->enum('tipo', ['COMPRA', 'VENTA']);
            $table->boolean('incluye_iva')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('price_lists'); }
};
