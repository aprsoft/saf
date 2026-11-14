<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model {
    protected $table = 'inventory_movements';
    protected $fillable = ['product_service_id', 'warehouse_id', 'tipo', 'cantidad_base', 'costo_unitario_base', 'referencia', 'observacion'];
    protected function casts(): array { return ['cantidad_base'=>'decimal:6','costo_unitario_base'=>'decimal:4']; }
    public function product() { return $this->belongsTo(ProductService::class, 'product_service_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
