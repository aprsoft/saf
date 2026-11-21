<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_emission_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained('documents');
            $table->string('idempotency_key', 100)->unique();
            $table->string('estado', 20);
            $table->unsignedInteger('folio')->nullable();
            $table->string('mensaje', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_emission_attempts');
    }
};
