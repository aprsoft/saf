<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded=['id'];
    public $timestamps = false;

    public function cajaIntegrada(): bool
    {
        return $this->modo_cobro === 'CAJA_INTEGRADA';
    }
}
