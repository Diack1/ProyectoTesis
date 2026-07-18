<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Espacio;
use App\Models\Sensor;

class EspacioSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $codigoEspacio = 'E' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $codigoSensor = 'SENSOR_' . str_pad($i, 2, '0', STR_PAD_LEFT);

            $espacio = Espacio::create([
                'codigo' => $codigoEspacio,
                'descripcion' => 'Espacio de estacionamiento ' . $codigoEspacio,
                'estado_actual' => 'libre',
                'activo' => true,
            ]);

            Sensor::create([
                'espacio_id' => $espacio->id,
                'codigo_sensor' => $codigoSensor,
                'tipo_sensor' => 'simulado',
                'estado' => 'activo',
            ]);
        }
    }
}