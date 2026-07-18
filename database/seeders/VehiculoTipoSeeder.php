<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehiculoTipo;

class VehiculoTipoSeeder extends Seeder
{
    public function run(): void
    {
        VehiculoTipo::updateOrCreate(
            ['codigo' => 'auto'],
            [
                'nombre' => 'Auto',
                'activo' => true,
            ]
        );

        VehiculoTipo::updateOrCreate(
            ['codigo' => 'moto'],
            [
                'nombre' => 'Moto',
                'activo' => true,
            ]
        );
    }
}
