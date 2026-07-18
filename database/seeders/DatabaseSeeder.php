<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            VehiculoTipoSeeder::class,
            EspacioSeeder::class,
            TarifaAvanzadaSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
