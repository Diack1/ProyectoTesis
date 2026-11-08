<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaCierre extends Model
{
    protected $fillable = ['inicio_at', 'fin_at', 'operador_id', 'efectivo_esperado', 'efectivo_contado', 'diferencia', 'totales_por_medio', 'pagos_count', 'reembolsos_count', 'caja_apertura_id', 'monto_inicial', 'ingresos_manuales', 'egresos_manuales', 'observacion'];
    protected function casts(): array { return ['inicio_at' => 'datetime', 'fin_at' => 'datetime', 'totales_por_medio' => 'array']; }
    public function apertura() { return $this->belongsTo(CajaApertura::class, 'caja_apertura_id'); }
    public function operador() { return $this->belongsTo(User::class, 'operador_id'); }
}
