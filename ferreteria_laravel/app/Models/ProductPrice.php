<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model {
    protected $table = 'product_prices';
    protected $fillable = ['product_service_id', 'unit_id', 'price_list_id', 'precio'];
    protected function casts(): array { return ['precio'=>'decimal:2']; }
    public function product() { return $this->belongsTo(ProductService::class, 'product_service_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function priceList() { return $this->belongsTo(PriceList::class); }
}
