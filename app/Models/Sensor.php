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

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['integracion_iot' => 'boolean', 'ultima_comunicacion_at' => 'datetime', 'ultima_lectura_valida_at' => 'datetime'];
    }

    public function getCalibradoAttribute(): bool
    {
        return $this->umbral_ocupado_cm !== null && $this->umbral_libre_cm !== null;
    }

    public function getLecturaVigenteAttribute(): bool
    {
        return $this->calibrado && $this->estado_estable !== null && $this->ultima_lectura_valida_at
            && $this->ultima_lectura_valida_at->gte(now()->subSeconds($this->segundos_sin_senal));
    }

    public function getConexionLabelAttribute(): string
    {
        if (! $this->integracion_iot) {
            return 'Integración pendiente';
        }
        if (! $this->ultima_comunicacion_at) {
            return 'Sin lecturas';
        }
        if ($this->ultima_comunicacion_at->lt(now()->subSeconds($this->segundos_sin_senal))) {
            return 'Sin conexión';
        }
        if (! $this->calibrado) {
            return 'En línea · Falta calibrar';
        }

        return $this->lectura_vigente ? 'En línea' : 'Sin lectura estable reciente';
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    public function registros()
    {
        return $this->hasMany(RegistroOcupacion::class, 'sensor_id');
    }
}
