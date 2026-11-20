<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('typedocument_id')->nullable(); 
            $table->enum('estado_documento', ['VIGENTE','NO VIGENTE'])->default('VIGENTE');
            $table->enum('condicion_documento', ['PAGADO','PENDIENTE','MOROSO','CONVENIO','ABONO|MOROSO','CONDONADO',
                'ANULADO','ELIMINADO','APLICADA','NO APLICADA','POR DEFINIR','RECHAZADO','CONTADO','CREDITO','SIN COSTO','INCOBRABLE','FINALIZADO','EN PROCESO'])->nullable();  

            //-------------------------------------------------------------

            $table->unsignedInteger('TpoDocRef')->nullable();
            $table->unsignedInteger('CodRef')->nullable();
            $table->longText('acuses')->nullable();
            $table->enum('fmaPago', ['1', '2', '3', 'NO INFORMADO'])->nullable(); 
            $table->enum('medioPago', ['', '2', '3', '4', '5','NO INFORMADO'])->nullable();

            $table->integer('TpoTranCompra')->nullable();
            $table->integer('TpoTranVenta')->nullable();
            $table->integer('CdgSIISucur')->nullable();          
            $table->string('RUTSolicita', 191)->nullable(); 
            
            //-------------------------------------------------------------            
                      
            $table->unsignedInteger('folio')->nullable();
            
            $table->date('fecha')->nullable();
            $table->date('fecha_vencimiento')->nullable();     
            $table->string('numero_orden', 191)->nullable();
            $table->binary('ted')->nullable();
            $table->date('fecha_resolucion')->nullable();
            $table->integer('numero_resolucion')->nullable();
            $table->string('nombre_referencia')->nullable();
            $table->longText('Observaciones')->nullable();
            $table->longText('Referencia')->nullable();
            $table->timestamps();

            //-------------------------------------------------------------            


            $table->foreign('parent_id')->references('id')->on('documents');            
            $table->foreign('typedocument_id')->references('id')->on('typedocuments');
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
