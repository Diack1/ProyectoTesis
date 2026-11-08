<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaCorreccion extends Model
{
    protected $table = 'venta_correcciones';
    protected $guarded = ['id'];
    protected function casts(): array { return ['antes' => 'array', 'despues' => 'array']; }
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
    public function ventaNueva() { return $this->belongsTo(Venta::class, 'venta_nueva_id'); }
}
