<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model {
    protected $table = 'product_units';
    protected $fillable = ['product_service_id', 'unit_id', 'factor_conversion'];
    protected function casts(): array { return ['factor_conversion'=>'decimal:6']; }
    public function product() { return $this->belongsTo(ProductService::class, 'product_service_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
}
