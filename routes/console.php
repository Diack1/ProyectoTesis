<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservas:expirar')->everyMinute()->withoutOverlapping();
Schedule::command('reservas:finalizar')->everyMinute()->withoutOverlapping();
Artisan::command('app:create-owner', function () {
    if (\App\Models\User::where('role', 'super_admin')->exists()) {
        $this->error('Ya existe un propietario. Este comando no modifica cuentas existentes.');
        return 1;
    }
    $data = ['name' => $this->ask('Nombre'), 'email' => $this->ask('Correo'),
        'password' => $this->secret('Contraseña (mínimo 14 caracteres)'),
        'password_confirmation' => $this->secret('Repite la contraseña')];
    $validator = validator($data, ['name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:14|confirmed']);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) { $this->error($error); }
        return 1;
    }
    \App\Models\User::create(\Illuminate\Support\Arr::only($data, ['name', 'email', 'password']) + ['role' => 'super_admin', 'activo' => true]);
    $this->info('Propietario creado.');
})->purpose('Crear un propietario sin credenciales predeterminadas');
