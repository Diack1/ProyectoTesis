<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    protected $table = 'sensores';

    protected $fillable = [
        'espacio_id',
        'codigo_sensor',
        'tipo_sensor',
        'estado',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    public function registros()
    {
        return $this->hasMany(RegistroOcupacion::class, 'sensor_id');
    }
}