<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detail extends Model
{
    protected $guarded = ['id'];

    public function productService()
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    } 

    public function scopeVigente($query)
    {
        return $query->where('estado_detalle', 'VIGENTE');
    }

  

} 
  