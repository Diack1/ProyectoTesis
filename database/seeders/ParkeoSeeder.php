<?php

namespace Database\Seeders;

use App\Models\Espacio;
use App\Models\VehiculoTipo;
use Illuminate\Database\Seeder;

class ParkeoSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 41; $i++) {
            $codigo = 'T'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (!Espacio::where('codigo', $codigo)->exists()) {
                Espacio::whereIn('codigo', ['T'.$i, 'E'.$i, 'E'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)])->update(['codigo' => $codigo]);
            }
            $e = Espacio::firstOrCreate(['codigo' => $codigo],
                ['descripcion' => 'Techo '.$i, 'estado_actual' => 'libre', 'activo' => true]);
            $e->update(['incluido_estudio' => $i <= 15]);
            if ($i >= 27) {
                $e->update(['modo_monitoreo' => 'manual']);
            }
            if (! $e->vehiculoTipos()->exists()) {
                $e->vehiculoTipos()->syncWithoutDetaching(VehiculoTipo::where('activo', true)->pluck('id'));
            }
        }
    }
}
