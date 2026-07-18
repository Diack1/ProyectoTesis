<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroOcupacion extends Model
{
    protected $table = 'registros_ocupacion';

    protected $fillable = [
        'espacio_id',
        'sensor_id',
        'estado_detectado',
        'distancia_cm',
        'fecha_hora',
        'origen',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }
}