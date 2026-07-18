<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    protected $table = 'tarifas';

    protected $fillable = [
        'vehiculo_tipo_id',
        'nombre',
        'tipo_tarifa',
        'monto_base',
        'monto_por_hora',
        'monto_por_fraccion',
        'minutos_fraccion',
        'tiempo_minimo_minutos',
        'tolerancia_minutos',
        'penalidad_por_fraccion',
        'hora_inicio',
        'hora_fin',
        'activo',
        'prioridad',
    ];

    protected function casts(): array
    {
        return [
            'monto_base' => 'decimal:2',
            'monto_por_hora' => 'decimal:2',
            'monto_por_fraccion' => 'decimal:2',
            'penalidad_por_fraccion' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function vehiculoTipo()
    {
        return $this->belongsTo(VehiculoTipo::class, 'vehiculo_tipo_id');
    }
}
