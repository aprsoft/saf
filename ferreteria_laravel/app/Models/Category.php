<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    protected $table = 'categories';
    protected $fillable = ['nombre', 'codigo', 'parent_id', 'activo'];
    protected function casts(): array { return ['activo'=>'boolean']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function products() { return $this->hasMany(ProductService::class); }
}
