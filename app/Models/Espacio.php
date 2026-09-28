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

    /** Preload status flags for read-only lists without loading reservation history. */
    public function scopeConEstadoOperativo($query)
    {
        return $query->withExists([
            'estadias as tiene_estadia_activa' => fn ($q) => $q->activas(),
            'reservas as tiene_reserva_bloqueante' => fn ($q) => $q
                ->whereIn('estado', ['pendiente_pago', 'confirmada'])->whereNull('inasistencia_at'),
        ]);
    }

    public function getEstadoActualAttribute($value)
    {
        if ($value !== 'mantenimiento' && ($this->attributes['tiene_estadia_activa'] ?? $this->estadias()->activas()->exists())) {
            return 'ocupado';
        }
        if ($value === 'libre' && $this->modo_monitoreo === 'sensor' && $this->sensor?->integracion_iot && ! $this->sensor->lectura_vigente) {
            return 'sin_senal';
        }
        if ($value === 'libre' && ($this->attributes['tiene_reserva_bloqueante'] ?? $this->reservas()->whereIn('estado', ['pendiente_pago', 'confirmada'])->whereNull('inasistencia_at')->exists())) {
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
