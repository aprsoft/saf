<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Customer;
use App\Models\Document;
use DomainException;
use Illuminate\Support\Facades\DB;

class serviceNotaCredito
{
    public function createDocument(Document $document, int $CodRef, ?Customer $receptor = null): Document
    {
        // Esta operación copia íntegramente los importes: solo corresponde a anulación total.
        if ($CodRef !== 1) {
            throw new DomainException('La creación por copia completa solo admite CodRef 1.');
        }

        return DB::transaction(function () use ($document, $receptor): Document {
            $original = Document::query()->with(['customer', 'typedocument'])->lockForUpdate()->findOrFail($document->id);
            if (! in_array((int) $original->typedocument?->codigo, [33, 39], true)
                || ! $original->folio || $original->estado_documento !== 'VIGENTE') {
                throw new DomainException('Se requiere factura o boleta vigente con folio.');
            }
            if ($original->children()->whereHas('typedocument', fn ($q) => $q->where('codigo', 61))->exists()) {
                throw new DomainException('Existe una nota de crédito previa. Revise antes de anular.');
            }
            $cliente = $receptor ?? $original->customer;
            if ($cliente === null || blank($cliente->rut) || $cliente->rut === '66666666-6'
                || ($original->customer?->rut !== '66666666-6' && $cliente->id !== $original->customer_id)
                || ((int) $original->typedocument->codigo === 33 && $cliente->id !== $original->customer_id)) {
                throw new DomainException('La nota requiere receptor individualizado y autorizado.');
            }

            $tipo = \App\Models\Typedocument::query()->where('codigo', 61)->sole();
            $nota = Document::query()->create([
                'parent_id' => $original->id,
                'user_id' => auth()->id() ?? $original->user_id,
                'customer_id' => $cliente->id,
                'typedocument_id' => $tipo->id,
                'fecha' => today('America/Santiago')->toDateString(),
                'CodRef' => 1,
                'estado_documento' => 'VIGENTE',
                'condicion_documento' => 'POR DEFINIR',
                'descuento_global_tipo' => $original->descuento_global_tipo,
                'descuento_global_valor' => $original->descuento_global_valor,
            ]);
            foreach ($original->details()->vigente()->get() as $detalle) {
                $copia = $detalle->replicate();
                $copia->document_id = $nota->id;
                $copia->save();
            }
            if ($nota->total <= 0 || $nota->total !== $original->total) {
                throw new DomainException('Los importes de la anulación no cuadran.');
            }

            return $nota;
        });
    }
}
