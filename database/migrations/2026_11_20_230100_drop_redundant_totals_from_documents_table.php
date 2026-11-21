<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estos importes se calculan desde details y descuento_global_*.
     * La migración anterior permanece intacta por compatibilidad con
     * instalaciones en las que ya fue aplicada.
     */
    private const COLUMNAS = [
        'monto_neto',
        'monto_iva',
        'monto_exento',
        'monto_total',
        'valor_pagar',
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $columna) {
            if (Schema::hasColumn('documents', $columna)) {
                Schema::table('documents', static function (Blueprint $table) use ($columna): void {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    public function down(): void
    {
        // Solo restaura el esquema. Los valores históricos eliminados
        // no son recuperables mediante rollback.
        foreach (self::COLUMNAS as $columna) {
            if (! Schema::hasColumn('documents', $columna)) {
                Schema::table('documents', static function (Blueprint $table) use ($columna): void {
                    $table->unsignedBigInteger($columna)->default(0);
                });
            }
        }
    }
};
