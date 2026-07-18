<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\VehiculoTipo;

class Espacio extends Model
{
    protected $table = 'espacios';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado_actual',
        'activo',
    ];

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
