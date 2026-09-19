<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estadia extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hora_ingreso' => 'datetime', 'inicio_cobro' => 'datetime', 'hora_salida' => 'datetime', 'liquidado_hasta' => 'datetime',
            'tarifa_snapshot' => 'array', 'monto_adelantado' => 'decimal:2', 'monto_total' => 'decimal:2', 'monto_exceso' => 'decimal:2',
            'saldo_cobrado' => 'decimal:2', 'efectivo_recibido' => 'decimal:2', 'vuelto' => 'decimal:2',
        ];
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class);
    }

    public function vehiculoTipo()
    {
        return $this->belongsTo(VehiculoTipo::class);
    }

    public function reserva()
    {
        return $this->belongsTo(Reserva::class);
    }

    public function operadorIngreso()
    {
        return $this->belongsTo(User::class, 'operador_ingreso_id');
    }

    public function operadorSalida()
    {
        return $this->belongsTo(User::class, 'operador_salida_id');
    }

    public function pagoSalida()
    {
        return $this->hasOne(Pago::class);
    }

    public function scopeActivas($query)
    {
        return $query->whereNull('hora_salida');
    }

    public function getEstadoLabelAttribute(): string
    {
        return $this->hora_salida ? 'Finalizada' : ($this->inicio_cobro ? 'En estacionamiento' : 'Esperando sensor');
    }
}
