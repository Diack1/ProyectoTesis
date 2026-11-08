<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('app.initial_admin_password');
        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages(['password' => 'Define PARKEO_INITIAL_ADMIN_PASSWORD solo durante el aprovisionamiento inicial.']);
        }
        $email = config('app.initial_admin_email');
        validator(['email' => $email, 'password' => $password], [
            'email' => 'required|email|max:255',
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();
        if (User::where('email', $email)->exists()) {
            $this->command?->info('La cuenta inicial ya existe. No se modificó.');
            return;
        }
        $user = User::create(['name' => 'Administrador inicial', 'email' => $email,
            'password' => $password, 'role' => 'super_admin', 'activo' => true,
            'must_change_password' => false]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->command?->info('Cuenta administradora inicial creada. Puede ingresar con la contraseña asignada.');
    }
}
