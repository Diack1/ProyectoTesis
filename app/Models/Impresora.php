<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impresora extends Model
{
    protected $fillable = ['nombre','tipo','conexion','direccion_ip','puerto','papel_mm','predeterminada_unica'];
    protected function casts(): array { return ['predeterminada_unica'=>'boolean','papel_mm'=>'integer','puerto'=>'integer']; }
    public static function predeterminada(): ?self { return static::where('predeterminada_unica', 1)->first(); }
    public function directa(): bool { return $this->tipo==='termica' && in_array($this->conexion,['red','wifi'],true) && $this->direccion_ip; }
}
