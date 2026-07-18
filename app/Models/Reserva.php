<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $fillable = [
        'user_id',
        'espacio_id',
        'codigo_reserva',
        'fecha_reserva',
        'hora_inicio',
        'hora_fin',
        'duracion_minutos',
        'tarifa_hora',
        'monto_total',
        'estado',
        'expires_at',
        'pagado_at',
        'cancelado_at',
        'expirado_at',
        'observacion',
        'vehiculo_tipo_id',
        'tarifa_id',
        'tipo_vehiculo_nombre',
        'tarifa_nombre',
        'tipo_tarifa',
        'tolerancia_minutos',
        'penalidad_por_fraccion',
        'monto_penalidad',
    ];

    protected function casts(): array
    {
        return [
            'fecha_reserva' => 'date',
            'expires_at' => 'datetime',
            'pagado_at' => 'datetime',
            'cancelado_at' => 'datetime',
            'expirado_at' => 'datetime',
            'tarifa_hora' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'penalidad_por_fraccion' => 'decimal:2',
            'monto_penalidad' => 'decimal:2',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    public function vehiculoTipo()
    {
        return $this->belongsTo(VehiculoTipo::class, 'vehiculo_tipo_id');
    }

    public function tarifa()
    {
        return $this->belongsTo(Tarifa::class, 'tarifa_id');
    }

     public function pagos()
    {
        return $this->hasMany(Pago::class, 'reserva_id');
    }

    public function pagoAprobado()
    {
        return $this->hasOne(Pago::class, 'reserva_id')
            ->where('estado', 'aprobado');
    }
    
    public function reembolsos()
    {
        return $this->hasMany(Reembolso::class, 'reserva_id');
    }
    
    public function estaPendientePago(): bool
    {
        return $this->estado === 'pendiente_pago';
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'confirmada';
    }

    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    public function estaExpirada(): bool
    {
        return $this->estado === 'expirada';
    }
    
    public function requiereReembolso(): bool
    {
        return in_array($this->estado, [
            'reembolso_solicitado',
            'reembolso_aprobado',
            'reembolso_rechazado',
        ]);
    }
}