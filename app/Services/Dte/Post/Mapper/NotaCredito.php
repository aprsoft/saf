<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Mapper;

use App\Models\Company;
use App\Models\Document;
use DomainException;
use LogicException;

class NotaCredito
{
    private Document $document;
    private Company $company;

    public function __construct(Document $document)
    {
        $this->document = $document;
        $this->company = Company::query()->firstOrFail();
    }

    public function mapJsonDte(): string
    {
        if ((int) $this->document->typedocument?->codigo !== 61) {
            throw new LogicException('El mapper NotaCredito solo soporta TipoDTE 61.');
        }

        $customer = $this->document->customer;
       
        $parent = $this->document->parent;
        if ($customer === null || blank($customer->rut) || $customer->rut === '66666666-6' || blank(trim($customer->fullName)) || $parent?->typedocument === null || $parent->folio === null) {
            throw new DomainException('La nota requiere receptor y documento de referencia con folio.');
        }
        // CodRef existe físicamente en documents. No asumir que toda nota anula.
        if (! in_array((int) $this->document->CodRef, [1, 2, 3], true)) {
            throw new DomainException('La nota no tiene un CodRef válido.');
        }
        $totales = $this->document->calcularTotales();
        $referencia = [[
            'NroLinRef' => 1,
            'TpoDocRef' => (int) $parent->typedocument->codigo,
            'FolioRef'  => (int) $parent->folio,
            'FchRef'    => $parent->fecha,
            'CodRef'    => (int) $this->document->CodRef,
            'RazonRef' => match ((int) $this->document->CodRef) { 1 => 'Anula documento de referencia', 2 => 'Corrige texto', 3 => 'Corrige montos' },
        ]];

        $dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 61,
                    'Folio'   => 0,
                    'FchEmis' => $this->document->fecha,
                    'MntBruto' => 1
                ],
                'Emisor' => [
                    'RUTEmisor' => $this->company->rut_emisor,
                    'RznSoc'    => $this->company->razon_social,
                    'GiroEmis'  => $this->company->giro_emisor,
                    'Acteco'    => $this->company->acteco,
                    'DirOrigen' => $this->company->direccion_origen,
                    'CmnaOrigen'=> $this->company->comuna_origen,
                ],
                'Receptor' => [
                    'RUTRecep'    => $customer->rut,
                    'RznSocRecep' => $customer->fullName,
                    'GiroRecep'   => $customer->giro,
                    'DirRecep'    => $customer->direccion_cliente,
                    'CmnaRecep'   => $customer->comuna,
                ],
                'Totales' => [
                    'MntNeto'  => $totales['monto_neto'],
                    'MntExe' => $totales['monto_exento'],
                    'TasaIVA'  => 19,
                    'IVA'      => $this->document->iva,
                    'MntTotal' => $this->document->total,
                ],
            ],
            'Detalle'    => $this->mapDetails(),
            'Referencia' => $referencia,
        ];

        $descuentos = $this->document->lineasDescuentoDte($totales);
        if ($descuentos !== []) {
            $dte['DscRcgGlobal'] = $descuentos;
        }

        return json_encode(
            ['response' => ['TIMBRE', 'FOLIO', 'XML', 'PDF'], 'dte' => $dte],
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
