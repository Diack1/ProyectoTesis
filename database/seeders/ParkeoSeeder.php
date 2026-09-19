<?php

namespace Database\Seeders;

use App\Models\Espacio;
use App\Models\VehiculoTipo;
use Illuminate\Database\Seeder;

class ParkeoSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $e = Espacio::firstOrCreate(['codigo' => 'E'.str_pad($i, 2, '0', STR_PAD_LEFT)],
                ['descripcion' => 'Espacio '.$i, 'estado_actual' => 'libre', 'activo' => true]);
            $e->update(['incluido_estudio' => $i <= 15]);
            if (! $e->vehiculoTipos()->exists()) {
                $e->vehiculoTipos()->syncWithoutDetaching(VehiculoTipo::where('activo', true)->pluck('id'));
            }
        }
    }
}
