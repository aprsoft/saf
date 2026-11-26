<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Mapper;

use App\Models\Company;
use App\Models\Document;
use DomainException;
use LogicException;

final class FacturaAfecta
{
    public function __construct(private Document $document) {}

    public function mapJsonDte(): string
    {
        if ((int) $this->document->typedocument?->codigo !== 33) {
            throw new LogicException('El mapper FacturaAfecta solo soporta TipoDTE 33.');
        }

        $customer = $this->document->customer
            ?? throw new DomainException('El documento no tiene receptor.');
        $company = Company::query()->firstOrFail();
        $totales = $this->document->calcularTotales();

        if ($totales['monto_neto'] <= 0) {
            throw new DomainException('La factura afecta requiere un monto neto positivo.');
        }

        // Los detalles de SAF expresan precios brutos, incluso para la factura.
        $idDoc = [
            'TipoDTE' => 33,
            'Folio' => 0,
            'FchEmis' => $this->document->fecha,
            'MntBruto' => 1,
        ];
        if ($this->document->TpoTranCompra !== null) {
            $idDoc['TpoTranCompra'] = (int) $this->document->TpoTranCompra;
        }
        if ($this->document->TpoTranVenta !== null) {
            $idDoc['TpoTranVenta'] = (int) $this->document->TpoTranVenta;
        }
        $formaPago = match ((string) $this->document->fmaPago) {
            '1', 'CONTADO' => 1,
            '2', 'CREDITO' => 2,
            '3', 'SIN COSTO (ENTREGA GRATUITA)' => 3,
            '', 'NO INFORMADO' => null,
            default => throw new DomainException('Forma de pago no reconocida.'),
        };
        if ($formaPago !== null) {
            $idDoc['FmaPago'] = $formaPago;
        }

        $dte = [
            'Encabezado' => [
                'IdDoc' => $idDoc,
                'Emisor' => [
                    'RUTEmisor' => $company->rut_emisor,
                    'RznSoc' => $company->razon_social,
                    'GiroEmis' => $company->giro_emisor,
                    'Acteco' => $company->acteco,
                    'DirOrigen' => $company->direccion_origen,
                    'CmnaOrigen' => $company->comuna_origen,
                ],
                'Receptor' => [
                    'RUTRecep' => $customer->rut,
                    'RznSocRecep' => $customer->fullName,
                    'GiroRecep' => $customer->giro,
                    'DirRecep' => $customer->direccion_cliente,
                    'CmnaRecep' => $customer->comuna,
                ],
                'Totales' => [
                    'MntNeto' => $totales['monto_neto'],
                    'MntExe' => $totales['monto_exento'],
                    'TasaIVA' => 19,
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
