<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Espacio;
use App\Models\Sensor;
use App\Models\VehiculoTipo;

class EspacioSeeder extends Seeder
{
    public function run(): void
    {
        $vehiculoTipoIds = VehiculoTipo::where('activo', true)
            ->pluck('id')
            ->all();

        for ($i = 1; $i <= 5; $i++) {
            $codigoEspacio = 'E' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $codigoSensor = 'SENSOR_' . str_pad($i, 2, '0', STR_PAD_LEFT);

            $espacio = Espacio::firstOrCreate(
                ['codigo' => $codigoEspacio],
                [
                    'descripcion' => 'Espacio de estacionamiento ' . $codigoEspacio,
                    'estado_actual' => 'libre',
                    'activo' => true,
                ]
            );

            if ($vehiculoTipoIds) {
                $espacio->vehiculoTipos()->syncWithoutDetaching($vehiculoTipoIds);
            }

            Sensor::updateOrCreate(
                ['codigo_sensor' => $codigoSensor],
                [
                    'espacio_id' => $espacio->id,
                    'tipo_sensor' => 'simulado',
                    'estado' => 'activo',
                ]
            );
        }
    }
}
