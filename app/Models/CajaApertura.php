<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaApertura extends Model
{
    protected $fillable = ['nombre', 'monto_inicial', 'operador_id', 'inicio_at', 'abierta_unica'];

    protected function casts(): array
    {
        return ['monto_inicial' => 'decimal:2', 'inicio_at' => 'datetime'];
    }

    public function operador() { return $this->belongsTo(User::class, 'operador_id'); }
    public function movimientos() { return $this->hasMany(CajaMovimiento::class); }
    public function cierre() { return $this->hasOne(CajaCierre::class); }
}
