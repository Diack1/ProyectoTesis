<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'superadmin@cochera.com',
            ],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('SuperAdmin123'),
                'role' => 'super_admin',
                'activo' => true,
            ]
        );
    }
}