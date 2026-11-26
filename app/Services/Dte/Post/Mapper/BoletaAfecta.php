<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Mapper;

use App\Models\Company;
use App\Models\Document;
use DomainException;
use LogicException;

final class BoletaAfecta
{
    public function __construct(private Document $document) {}

    public function mapJsonDte(): string
    {
        if ((int) $this->document->typedocument?->codigo !== 39) {
            throw new LogicException('El mapper BoletaAfecta solo soporta TipoDTE 39.');
        }

        $customer = $this->document->customer
            ?? throw new DomainException('El documento no tiene receptor.');
        $company = Company::query()->firstOrFail();
        $totales = $this->document->calcularTotales();

        $dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 39,
                    'Folio' => 0,
                    'FchEmis' => $this->document->fecha,
                    'IndServicio' => 3,
                    'MedioPago' => (int) ($this->document->medioPago ?: 1),
                ],
                'Emisor' => [
                    'RUTEmisor' => $company->rut_emisor,
                    'RznSocEmisor' => $company->razon_social,
                    'GiroEmisor' => $company->giro_emisor,
                    'DirOrigen' => $company->direccion_origen,
                    'CmnaOrigen' => $company->comuna_origen,
                ],
                'Receptor' => [
                    'RUTRecep' => $customer->rut,
                    'RznSocRecep' => $customer->fullName,
                    'DirRecep' => $customer->direccion_cliente,
                    'CmnaRecep' => $customer->comuna,
                ],
                'Totales' => [
                    'MntNeto' => $totales['monto_neto'],
                    'MntExe' => $totales['monto_exento'],
                    'IVA' => $totales['monto_iva'],
                    'MntTotal' => $totales['monto_total'],
                ],
            ],
            'Detalle' => $this->mapDetails(),
        ];

        $descuentos = $this->document->lineasDescuentoDte($totales);
        if ($descuentos !== []) {
            $dte['DscRcgGlobal'] = $descuentos;
        }

        return json_encode(
            ['response' => ['TIMBRE', 'FOLIO', 'XML','PDF'], 'dte' => $dte],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function mapDetails(): array
    {
        $detalles = $this->document->details()
            ->vigente()->with('productService')->orderBy('id')->get();
        if ($detalles->isEmpty()) {
            throw new DomainException('El documento no tiene ítems vigentes para el DTE.');
        }

        return $detalles->values()->map(static function ($detalle, int $index): array {
            if ($detalle->QtyItem === null || $detalle->PrcItem === null || $detalle->MontoItem === null) {
                throw new DomainException('El detalle tiene cantidad, precio o monto incompletos.');
            }
            $linea = [
                'NroLinDet' => $index + 1,
                'NmbItem' => $detalle->productService?->nombre ?? $detalle->detalle ?? 'PRODUCTO',
                'QtyItem' => (float) $detalle->QtyItem,
                'PrcItem' => (int) $detalle->PrcItem,
                'MontoItem' => (int) $detalle->MontoItem,
            ];
            if ($detalle->IndExe !== null) {
                $linea['IndExe'] = (int) $detalle->IndExe;
            }
            if ((int) $detalle->DescuentoMonto > 0) {
                if ($detalle->DescuentoPct !== null) {
                    $linea['DescuentoPct'] = (int) $detalle->DescuentoPct;
                }
                $linea['DescuentoMonto'] = (int) $detalle->DescuentoMonto;
            }

            return $linea;
        })->all();
    }
}
