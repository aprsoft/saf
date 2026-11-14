<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model {
    protected $table = 'units';
    protected $fillable = ['nombre', 'simbolo', 'permite_decimales'];
    protected function casts(): array { return ['permite_decimales'=>'boolean']; }
    
}
