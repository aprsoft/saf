<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Document;
use DomainException;
use Illuminate\Validation\ValidationException;

/** Descuento global sobre precios brutos CLP, después de descuentos por línea. */
final class GlobalDiscountService
{
    public function calcular(iterable $detalles, string $tipo = 'porcentaje', int $valor = 0): array
    {
        if (! in_array($tipo, ['porcentaje', 'monto'], true) || $valor < 0 ||
            ($tipo === 'porcentaje' && $valor > 100)) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'Tipo o valor de descuento inválido.',
            ]);
        }

        $base = app(DocumentItemService::class)->totalizarDetalles($detalles);
        $afecto = $base['monto_neto'] + $base['monto_iva'];
        $exento = $base['monto_exento'];
        $subtotal = $afecto + $exento;

        if ($subtotal !== $base['monto_total']) {
            throw new DomainException('Los detalles no cuadran entre neto, IVA, exento y total.');
        }

        if ($valor === 0) {
            return $base + [
                'subtotal' => $subtotal,
                'descuento_global' => 0,
                'descuento_afecto' => 0,
                'descuento_exento' => 0,
            ];
        }

        foreach ($detalles as $detalle) {
            if (in_array((int) $detalle->IndExe, [2, 6], true)) {
                throw ValidationException::withMessages([
                    'form.descuentoValor' => 'El descuento sobre conceptos no facturables requiere un tratamiento independiente.',
                ]);
            }
        }

        if ($subtotal <= 0 || ($tipo === 'monto' && $valor >= $subtotal)) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'El descuento debe ser menor que el total de la nota.',
            ]);
        }

        if ($tipo === 'porcentaje') {
            $descuentoAfecto = intdiv($afecto * $valor + 50, 100);
            $descuentoExento = intdiv($exento * $valor + 50, 100);
        } else {
            $descuentoAfecto = min($afecto, intdiv($valor * $afecto + intdiv($subtotal, 2), $subtotal));
            $descuentoExento = $valor - $descuentoAfecto;
            if ($descuentoExento > $exento) {
                $descuentoExento = $exento;
                $descuentoAfecto = $valor - $descuentoExento;
            }
        }

        $descuento = $descuentoAfecto + $descuentoExento;
        if ($descuento >= $subtotal) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'El descuento no puede dejar el documento en cero.',
            ]);
        }

        $afectoFinal = $afecto - $descuentoAfecto;
        $neto = intdiv($afectoFinal * 100 + 59, 119);

        return [
            'monto_neto' => $neto,
            'monto_iva' => $afectoFinal - $neto,
            'monto_exento' => $exento - $descuentoExento,
            'monto_total' => $subtotal - $descuento,
            'subtotal' => $subtotal,
            'descuento_global' => $descuento,
            'descuento_afecto' => $descuentoAfecto,
            'descuento_exento' => $descuentoExento,
        ];
    }

    public function paraDocumento(Document $documento): array
    {
        return $this->calcular(
            $documento->details()->vigente()->get(),
            $documento->descuento_global_tipo ?? 'porcentaje',
            (int) ($documento->descuento_global_valor ?? 0),
        );
    }

    /** DscRcgGlobal: una línea por tratamiento de IVA, según SII. */
    public function lineasDte(Document $documento, array $totales): array
    {
        if ($totales['descuento_global'] === 0) {
            return [];
        }

        $tipo = $documento->descuento_global_tipo;
        $porcentaje = $tipo === 'porcentaje';
        $lineas = [];

        foreach ([
            ['monto' => 'descuento_afecto', 'glosa' => 'DESCUENTO GLOBAL AFECTO', 'ind_exe' => null],
            ['monto' => 'descuento_exento', 'glosa' => 'DESCUENTO GLOBAL EXENTO', 'ind_exe' => 1],
        ] as $grupo) {
            if ($totales[$grupo['monto']] === 0) {
                continue;
            }

            $linea = [
                'NroLinDR' => count($lineas) + 1,
                'TpoMov' => 'D',
                'GlosaDR' => $grupo['glosa'],
                'TpoValor' => $porcentaje ? '%' : '$',
                'ValorDR' => $porcentaje
                    ? (int) $documento->descuento_global_valor
                    : $totales[$grupo['monto']],
            ];
            if ($grupo['ind_exe'] !== null) {
                $linea['IndExeDR'] = $grupo['ind_exe'];
            }
            $lineas[] = $linea;
        }

        return $lineas;
    }
}
