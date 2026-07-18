<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tarifa;
use App\Models\VehiculoTipo;

class TarifaAvanzadaSeeder extends Seeder
{
    public function run(): void
    {
        $auto = VehiculoTipo::where('codigo', 'auto')->first();
        $moto = VehiculoTipo::where('codigo', 'moto')->first();

        if (!$auto || !$moto) {
            return;
        }

        Tarifa::updateOrCreate(
            [
                'nombre' => 'Tarifa auto por hora',
                'vehiculo_tipo_id' => $auto->id,
                'tipo_tarifa' => 'por_hora',
            ],
            [
                'monto_base' => 0,
                'monto_por_hora' => 5.00,
                'monto_por_fraccion' => 2.50,
                'minutos_fraccion' => 30,
                'tiempo_minimo_minutos' => 60,
                'tolerancia_minutos' => 10,
                'penalidad_por_fraccion' => 3.00,
                'hora_inicio' => null,
                'hora_fin' => null,
                'activo' => true,
                'prioridad' => 1,
            ]
        );

        Tarifa::updateOrCreate(
            [
                'nombre' => 'Tarifa moto por hora',
                'vehiculo_tipo_id' => $moto->id,
                'tipo_tarifa' => 'por_hora',
            ],
            [
                'monto_base' => 0,
                'monto_por_hora' => 3.00,
                'monto_por_fraccion' => 1.50,
                'minutos_fraccion' => 30,
                'tiempo_minimo_minutos' => 60,
                'tolerancia_minutos' => 10,
                'penalidad_por_fraccion' => 2.00,
                'hora_inicio' => null,
                'hora_fin' => null,
                'activo' => true,
                'prioridad' => 1,
            ]
        );

        Tarifa::updateOrCreate(
            [
                'nombre' => 'Tarifa nocturna auto',
                'vehiculo_tipo_id' => $auto->id,
                'tipo_tarifa' => 'nocturna',
            ],
            [
                'monto_base' => 12.00,
                'monto_por_hora' => 0,
                'monto_por_fraccion' => null,
                'minutos_fraccion' => null,
                'tiempo_minimo_minutos' => 480,
                'tolerancia_minutos' => 15,
                'penalidad_por_fraccion' => 5.00,
                'hora_inicio' => '22:00:00',
                'hora_fin' => '06:00:00',
                'activo' => true,
                'prioridad' => 10,
            ]
        );
    }
}
