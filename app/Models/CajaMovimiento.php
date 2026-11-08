<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    protected $fillable = ['caja_apertura_id', 'operador_id', 'tipo', 'metodo_pago', 'monto', 'motivo', 'venta_correccion_id', 'ajuste_venta'];

    protected function casts(): array { return ['monto' => 'decimal:2']; }

    public function operador() { return $this->belongsTo(User::class, 'operador_id'); }
}
