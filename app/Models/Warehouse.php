<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model {
    protected $table = 'warehouses';
    protected $fillable = ['codigo', 'nombre', 'ubicacion', 'activo'];
    protected function casts(): array { return ['activo'=>'boolean']; }
    public function movements() { return $this->hasMany(InventoryMovement::class); }
}
