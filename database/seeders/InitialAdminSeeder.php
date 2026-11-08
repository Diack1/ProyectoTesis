<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('PARKEO_INITIAL_ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages(['password' => 'Define PARKEO_INITIAL_ADMIN_PASSWORD solo durante el aprovisionamiento inicial.']);
        }
        $email = 'frett.tec2@gmail.com';
        if (User::where('email', $email)->exists()) {
            $this->command?->info('La cuenta inicial ya existe. No se modificó.');
            return;
        }
        $user = User::create(['name' => 'Administrador inicial', 'email' => $email,
            'password' => Hash::make($password), 'role' => 'super_admin', 'activo' => true,
            'must_change_password' => false]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->command?->info('Cuenta administradora inicial creada. Puede ingresar con la contraseña asignada.');
    }
}
