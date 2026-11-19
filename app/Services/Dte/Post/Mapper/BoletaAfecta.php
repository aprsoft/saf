<?php

declare(strict_types=1);

namespace App\Services\Dte\Post\Mapper;

use App\Models\Company;
use App\Models\Document;
use DomainException;
use LogicException;

class BoletaAfecta
{
    private Document $document;
    private Company $company;

    public function __construct(Document $document)
    {
        $this->document = $document;
        $this->company  = Company::query()->firstOrFail();
    }

    public function mapJsonDte(): string
    {
        if ((int) $this->document->typedocument?->codigo !== 39) {
            throw new LogicException('El mapper BoletaAfecta solo soporta TipoDTE 39.');
        }

        $customer = $this->document->customer;
        if ($customer === null) {
            throw new DomainException('El documento no tiene receptor.');
        }

        $dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE'      => 39,
                    'Folio'        => 0,
                    'FchEmis'      => $this->document->fecha,
                    'IndServicio'  => 3,
                    'MedioPago'    => $this->medioPagoSii(),
                ],
                'Emisor' => [
                    'RUTEmisor'    => $this->company->rut_emisor,
                    'RznSocEmisor' => $this->company->razon_social,
                    'GiroEmisor'   => $this->company->giro_emisor,
                    'DirOrigen'    => $this->company->direccion_origen,
                    'CmnaOrigen'   => $this->company->comuna_origen,
                ],
                'Receptor' => [
                    'RUTRecep'     => $customer->rutDte,
                    'RznSocRecep'  => $customer->fullName,
                    'DirRecep'     => $customer->direccion_cliente,
                    'CmnaRecep'    => $customer->comuna,
                ],
                'Totales' => [
                    'MntExe'       => $this->document->exento,
                    'MntNeto'      => $this->document->neto,
                    'IVA'          => $this->document->iva,
                    'MntTotal'     => $this->document->total,
                    'TotalPeriodo' => $this->document->total,
                ],
            ],
            'Detalle' => $this->mapDetails(),
        ];

        

        return json_encode(
            ['response' => ['TIMBRE', 'FOLIO','XML'], 'dte' => $dte],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }


    
    protected function mapDetails(): array
    {
        $details = $this->document->details()
            ->vigente()
            ->with('productService')
            ->orderBy('id')
            ->get();

        if ($details->isEmpty()) {
            throw new DomainException(
                'El documento no tiene ítems vigentes para el DTE.'
            );
        }

        return $details->values()
            ->map(function ($detail, int $index): array {
                if (
                    $detail->productService === null ||
                    $detail->QtyItem === null ||
                    $detail->PrcItem === null ||
                    $detail->MontoItem === null
                ) {
                    throw new DomainException(
                        'El detalle tiene datos incompletos.'
                    );
                }

                $line = [
                    'NroLinDet' => $index + 1,
                    'NmbItem' => $detail->productService->nombre,
                    'QtyItem' => (float) $detail->QtyItem,
                    'PrcItem' => (int) $detail->PrcItem,
                    'MontoItem' => (int) $detail->MontoItem,
                ];

                if ($detail->IndExe !== null) {
                    $line['IndExe'] = (int) $detail->IndExe;
                }

                if ((int) $detail->DescuentoMonto > 0) {
                    if ($detail->DescuentoPct !== null) {
                        $line['DescuentoPct'] =
                            (float) $detail->DescuentoPct;
                    }

                    $line['DescuentoMonto'] =
                        (int) $detail->DescuentoMonto;
                }

                return $line;
            })
            ->all();
    }

    private function medioPagoSii(): int
    {
        return match ($this->document->medio_pago) {
            'EFECTIVO' => 1,

            'TARJETA DEBITO',
            'TARJETA CREDITO' => 2,

            'TRANSFERENCIA BANCARIA' => 3,

            'CHEQUE' => 4,

            'TARJETA COMERCIAL',
            'CREDITO INTERNO',
            'NOTA DE CREDITO',
            'OTRO' => 5,

            default => throw new DomainException(
                'Medio de pago no válido para boleta electrónica.'
            ),
        };
    }

   

}
