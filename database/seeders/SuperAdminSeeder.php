<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Privileged accounts are provisioned explicitly, never reset by database seeding.
        $this->command?->warn('Para crear el propietario usa: php artisan app:create-owner');
    }
}
