<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->enum('modo_cobro', [
                'CAJA_CENTRAL',
                'CAJA_INTEGRADA',
            ])->default('CAJA_CENTRAL');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('modo_cobro');
        });
    }
};
