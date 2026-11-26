<?php

namespace App\Models;

use DomainException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    protected $guarded = ['id'];

    public function errorDtes()
    {
        return $this->hasMany(ErrorDte::class);
    }

    public function emisionPendienteDeConciliar(): bool
    {
        return $this->errorDtes()
            ->whereIn('error->saf_emision', ['ENVIANDO', 'INCIERTA'])
            ->exists();
    }

    public function puedePagar(): bool
    {
        return (int) $this->typedocument_id === 2
            && $this->estado_documento === 'VIGENTE'
            && $this->condicion_documento === 'EN PROCESO'
            && $this->folio === null
            && ! DB::table('document_payment')->where('document_id', $this->id)->exists()
            && ! $this->emisionPendienteDeConciliar();
    }

    
    public function getExentoAttribute(): int
    {
        return $this->calcularTotales()['monto_exento'];
    }

    public function getNetoAttribute(): int
    {
        return $this->calcularTotales()['monto_neto'];
    }

    public function getIvaAttribute(): int
    {
        return $this->calcularTotales()['monto_iva'];
    }

    public function getTotalAttribute(): int
    {
        return $this->calcularTotales()['monto_total'];
    }


    public function getRutAttribute(): ?string
    {
        return $this->customer?->rutFormat
            ?? $this->supplier?->rutFormat
            ?? $this->user?->rutFormat;
    }

    // Esta funcion entrega la descripcion dependiendo del tipo y condicion del documento
	public function getDetalleAttribute(): string
    {
        if ($this->customer) {
            
            if ($this->parent) {
                return sprintf(
                    'REF : %s - %s',
                    $this->parent->typedocument->abrev,
                    $this->parent->folio
                );
            }
            
        }

        if ($this->supplier) {
            return 'COMPRA DE INSUMOS';
        }

        if ($this->employee) {
            return match ($this->typedocument->id) {
                7 => 'NOTA DE EGRESO',
                8 => 'NOTA DE INGRESO',
                default => 'POR DEFINIR',
            };
        }

        return 'POR DEFINIR';
    }

    public function getFullNameEntityAttribute(): string
    {
        return $this->customer?->fullName
            ?? $this->supplier?->fullName
            ?? $this->user?->fullName
            ?? 'POR DEFINIR';
    }

    public function parent()
    {
        return $this->belongsTo(Document::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Document::class, 'parent_id')
            ->where('estado_documento', 'VIGENTE');
    }

    public function details()
    {
        return $this->hasMany(Detail::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }  

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    } 

    public function typedocument() 
    {
        return $this->belongsTo(Typedocument::class);
    }   


    public function payment() {

        return $this->belongsToMany(Payment::class)
            ->withPivot('estado_abono','id','abono')
            ->wherePivot('estado_abono','VIGENTE')
            ->where('estado_pago','VIGENTE')
            ->withTimestamps();
    }

    //****************************** Scope ******************************//
   

		public function scopeVigente($query)
		{
			return $query->where('estado_documento', 'VIGENTE');
		}

        public function scopeNoVigente($query)
		{
			return $query->where('estado_documento', 'NO VIGENTE');
		}

        public function scopeSearchFullName($query, $busqueda)
        {
            $query->whereRaw(
                    "CONCAT_WS(
                        ' ',
                        customers.nombres,
                        customers.apellido_paterno,
                        customers.apellido_materno
                    ) LIKE CONCAT('%', REPLACE(?, ' ', '%'), '%')",
                    [$busqueda]
                );
        }

    /**
     * Ventas: importes con descuentos por línea y globales.
     * Compras: suma los importes persistidos en los detalles.
     */
    public function calcularTotales(): array
    {
        $detalles = $this->details()->vigente()->get();

        if ($this->supplier_id !== null && $this->customer_id === null) {
            return self::totalizarDetalles($detalles);
        }

        return self::calcularDescuentoGlobal(
            $detalles,
            $this->descuento_global_tipo ?? 'porcentaje',
            (int) ($this->descuento_global_valor ?? 0),
        );
    }

    public static function totalizarDetalles(iterable $detalles): array
    {
        $totales = [
            'monto_neto' => 0,
            'monto_iva' => 0,
            'monto_exento' => 0,
            'monto_total' => 0,
        ];

        foreach ($detalles as $detalle) {
            if ($detalle->estado_detalle !== 'VIGENTE') {
                continue;
            }

            $totales['monto_neto'] += (int) $detalle->MontoNetoItem;
            $totales['monto_iva'] += (int) $detalle->ivaItem;
            $totales['monto_exento'] += (int) $detalle->MontoExeItem;
            $totales['monto_total'] += (int) $detalle->MontoItem;
        }

        return $totales;
    }

    public static function calcularDescuentoGlobal(iterable $detalles, string $tipo = 'porcentaje', int $valor = 0): array
    {
        if (! in_array($tipo, ['porcentaje', 'monto'], true) || $valor < 0 ||
            ($tipo === 'porcentaje' && $valor > 100)) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'Tipo o valor de descuento inválido.',
            ]);
        }

        $base = self::totalizarDetalles($detalles);
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

    /** DscRcgGlobal: una línea por tratamiento de IVA, según SII. */
    public function lineasDescuentoDte(array $totales): array
    {
        if ($totales['descuento_global'] === 0) {
            return [];
        }

        $tipo = $this->descuento_global_tipo;
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
                    ? (int) $this->descuento_global_valor
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
