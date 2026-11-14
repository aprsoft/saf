<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model {
    protected $table = 'price_lists';
    protected $fillable = ['nombre', 'tipo', 'incluye_iva', 'activo'];
    protected function casts(): array { return ['incluye_iva'=>'boolean','activo'=>'boolean']; }
    public function prices() { return $this->hasMany(ProductPrice::class); }
}
