<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Espacio extends Model
{
    protected $table = 'espacios';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado_actual',
        'activo', 'incluido_estudio', 'modo_monitoreo',
    ];

    public function estadias()
    {
        return $this->hasMany(Estadia::class);
    }

    public function getEstadoActualAttribute($value)
    {
        if ($value !== 'mantenimiento' && $this->estadias()->activas()->exists()) {
            return 'ocupado';
        }
        if ($value === 'libre' && $this->modo_monitoreo === 'sensor' && $this->sensor?->integracion_iot && ! $this->sensor->lectura_vigente) {
            return 'sin_senal';
        }
        if ($value === 'libre' && $this->reservas()->whereIn('estado', ['pendiente_pago', 'confirmada'])->whereNull('inasistencia_at')->exists()) {
            return 'reservado';
        }

        return $value;
    }

    public function sensor()
    {
        return $this->hasOne(Sensor::class, 'espacio_id');
    }

    public function registros()
    {
        return $this->hasMany(RegistroOcupacion::class, 'espacio_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'espacio_id');
    }

    public function reservaActiva()
    {
        return $this->hasOne(Reserva::class, 'espacio_id')
            ->whereIn('estado', ['pendiente_pago', 'confirmada']);
    }

    public function vehiculoTipos()
    {
        return $this->belongsToMany(
            VehiculoTipo::class,
            'espacio_vehiculo_tipo',
            'espacio_id',
            'vehiculo_tipo_id'
        )->withTimestamps();
    }
}
