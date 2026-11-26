<?php

namespace App\Models;

use App\Services\Sales\DocumentItemService;
use App\Services\Sales\GlobalDiscountService;
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
         * Obtiene los importes desde los servicios existentes.
         *
         * Ventas: considera descuentos individuales y globales.
         * Compras: suma los importes persistidos en details.
         */
        
        public function calcularTotales(): array
        {
            if (
                $this->supplier_id !== null
                && $this->customer_id === null
            ) {
                return app(DocumentItemService::class)
                    ->totalizarDetalles(
                        $this->details()->vigente()->get()
                    );
            }

            return app(GlobalDiscountService::class)
                ->paraDocumento($this);
        }

 

}
