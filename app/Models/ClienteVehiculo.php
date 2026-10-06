<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteVehiculo extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visitas()
    {
        return $this->hasMany(Estadia::class, 'placa', 'placa')->whereNotNull('hora_salida');
    }
}
