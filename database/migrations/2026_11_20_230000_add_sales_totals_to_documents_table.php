<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DocumentItemService ya persiste estos campos, pero la migración base
     * de documents de feature/sales-counter-v2 no los define.
     */
    public function up(): void
    {
        foreach (['monto_neto', 'monto_iva', 'monto_exento', 'monto_total', 'valor_pagar'] as $column) {
            if (! Schema::hasColumn('documents', $column)) {
                Schema::table('documents', function (Blueprint $table) use ($column): void {
                    $table->unsignedBigInteger($column)->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        // Conservador: no eliminar columnas que pudieran existir antes de
        // esta migración por cambios locales históricos. Reversión manual
        // solo tras verificar el esquema y el uso de cada columna.
    }
};
