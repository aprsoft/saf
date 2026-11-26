<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dte_emission_attempts', function (Blueprint $table): void {
            $table->string('forma_pago', 40)->nullable();
            $table->unsignedBigInteger('monto')->nullable();
            $table->unsignedBigInteger('monto_recibido')->nullable();
            $table->unsignedBigInteger('tipo_documento_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dte_emission_attempts', function (Blueprint $table): void {
            $table->dropColumn(['forma_pago', 'monto', 'monto_recibido', 'tipo_documento_id']);
        });
    }
};
