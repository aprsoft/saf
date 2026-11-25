<?php

namespace App\Models;

use Freshwork\ChileanBundle\Rut;
use Freshwork\ChileanBundle\RutFormat;
use Illuminate\Database\Eloquent\Model;


class Customer extends Model
{
	protected $guarded = ['id'];
	// protected $with = ['meter'];

//------------------ Relaciones --------------------//

	public function documents() {
        return $this->hasMany(Document::class);		
    }

//------------------ Accesor/Mutator --------------------//

	public function getFullNameAttribute() 
	{
		return $this->nombres.' '. $this->apellido_paterno.' '. $this->apellido_materno;
	}

	public function getRutFormatAttribute()
	{
        return Rut::parse($this->rut)->validate();
    }

	public function getRutDteAttribute(): string
	{
		return Rut::parse($this->rut)->format();
	}

	public function setRutAttribute($value)
	{
        $this->attributes['rut'] = Rut::parse($value)->format(Rut::FORMAT_WITH_DASH);
		// Rut::parse('12.345.678-5')->normalize(); //return '123456785'
    }

	// protected function casts(): array
    // {
    //     return [
    //         'rut' => Rut::parse($this->rut)->format(RutFormat::WithDash),
          
    //     ];
    // }


//-------------------------- functions --------------------------//

public function isCompany(): bool
	{
        return ($this->condicion_cliente === 'EMPRESA')? true:false;
    }
}