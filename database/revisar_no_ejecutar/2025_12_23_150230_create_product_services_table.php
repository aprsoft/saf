<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nombre'); 
            $table->enum('unidad', ['KILO','UNIDAD','METRO','LITRO','GALON','CC','CAJA','',''])->default('VIGENTE');           
            $table->unsignedInteger('PrcExeItem')->nullable();
            $table->unsignedInteger('PrcNetoItem')->nullable();
            $table->unsignedInteger('PrcItem')->nullable();
            $table->unsignedInteger('MontoExeItem')->nullable();  
            $table->unsignedInteger('MontoNetoItem')->nullable(); 
            $table->unsignedInteger('MontoItem')->nullable();  
            $table->integer('ivaItem')->nullable();
            $table->unsignedInteger('porcentaje_iva')->nullable();
            $table->string('obs', 191)->nullable();
            $table->enum('estado', ['VIGENTE','NO VIGENTE'])->default('VIGENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_services');
    }
};
