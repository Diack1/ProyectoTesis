<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoTipo extends Model
{
    protected $table = 'vehiculo_tipos';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tarifas()
    {
        return $this->hasMany(Tarifa::class, 'vehiculo_tipo_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'vehiculo_tipo_id');
    }

    public function espacios()
    {
        return $this->belongsToMany(
            Espacio::class,
            'espacio_vehiculo_tipo',
            'vehiculo_tipo_id',
            'espacio_id'
        )->withTimestamps();
    }
}
