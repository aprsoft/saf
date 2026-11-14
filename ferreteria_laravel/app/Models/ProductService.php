<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductService extends Model {
    use SoftDeletes;
    protected $table = 'product_services';
    protected $fillable = ['category_id', 'codigo', 'nombre', 'tipo', 'stock_unit_id', 'purchase_unit_id', 'sales_unit_id', 'controla_stock', 'afecto_iva', 'activo', 'obs'];
    protected function casts(): array { return ['controla_stock'=>'boolean','afecto_iva'=>'boolean','activo'=>'boolean']; }
    public function category() { return $this->belongsTo(Category::class); }
    public function stockUnit() { return $this->belongsTo(Unit::class, 'stock_unit_id'); }
    public function purchaseUnit() { return $this->belongsTo(Unit::class, 'purchase_unit_id'); }
    public function salesUnit() { return $this->belongsTo(Unit::class, 'sales_unit_id'); }
    public function units() { return $this->hasMany(ProductUnit::class); }
    public function prices() { return $this->hasMany(ProductPrice::class); }
    public function inventoryMovements() { return $this->hasMany(InventoryMovement::class); }
}
