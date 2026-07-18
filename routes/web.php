<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\MonitoreoController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\Admin\ReservaAdminController;
use App\Http\Controllers\SuperAdmin\AdminUserController;
use App\Http\Controllers\Admin\TarifaAdminController;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicController::class, 'home'])
    ->name('public.home');

Route::get('/disponibilidad', [PublicController::class, 'disponibilidad'])
    ->name('public.disponibilidad');

Route::get('/tarifas', [PublicController::class, 'tarifas'])
    ->name('public.tarifas');

/*
|--------------------------------------------------------------------------
| Rutas de usuario autenticado
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Rutas de usuario normal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/reservas', [ReservaController::class, 'index'])
        ->name('reservas.index');

    Route::get('/reservas/crear/{espacio}', [ReservaController::class, 'create'])
        ->name('reservas.create');

    Route::post('/reservas/confirmar/{espacio}', [ReservaController::class, 'confirmar'])
        ->name('reservas.confirmar');

    Route::post('/reservas/guardar/{espacio}', [ReservaController::class, 'store'])
        ->name('reservas.store');

    Route::get('/reservas/{reserva}/pago', [PagoController::class, 'show'])
        ->name('pagos.show');

    Route::post('/reservas/{reserva}/pago-simulado', [PagoController::class, 'pagarSimulado'])
        ->name('pagos.simulado');

    Route::post('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar'])
        ->name('reservas.cancelar');

    Route::post('/reservas/{reserva}/solicitar-reembolso', [ReservaController::class, 'solicitarReembolso'])
        ->name('reservas.solicitarReembolso');
});

/*
|--------------------------------------------------------------------------
| Rutas de administrador y super administrador
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/monitoreo', [MonitoreoController::class, 'index'])
            ->name('monitoreo.index');

        Route::post('/espacios/{espacio}/estado', [MonitoreoController::class, 'cambiarEstado'])
            ->name('espacios.estado');

        Route::get('/espacios', [EspacioController::class, 'index'])
            ->name('espacios.index');

        Route::get('/espacios/crear', [EspacioController::class, 'create'])
            ->name('espacios.create');

        Route::post('/espacios', [EspacioController::class, 'store'])
            ->name('espacios.store');

        Route::get('/espacios/{espacio}/editar', [EspacioController::class, 'edit'])
            ->name('espacios.edit');

        Route::put('/espacios/{espacio}', [EspacioController::class, 'update'])
            ->name('espacios.update');

        Route::delete('/espacios/{espacio}', [EspacioController::class, 'destroy'])
            ->name('espacios.destroy');

        Route::get('/reportes', [ReporteController::class, 'index'])
            ->name('reportes.index');

        Route::get('/reportes/exportar-csv', [ReporteController::class, 'exportarCsv'])
            ->name('reportes.exportarCsv');

        Route::get('/reservas', [ReservaAdminController::class, 'index'])
            ->name('reservas.index');

        Route::post('/reembolsos/{reembolso}/aprobar', [ReservaAdminController::class, 'aprobarReembolso'])
            ->name('reembolsos.aprobar');

        Route::post('/reembolsos/{reembolso}/rechazar', [ReservaAdminController::class, 'rechazarReembolso'])
            ->name('reembolsos.rechazar');

        Route::get('/tarifas', [TarifaAdminController::class, 'index'])
            ->name('tarifas.index');

        Route::get('/tarifas/crear', [TarifaAdminController::class, 'create'])
            ->name('tarifas.create');

        Route::post('/tarifas', [TarifaAdminController::class, 'store'])
            ->name('tarifas.store');

        Route::get('/tarifas/{tarifa}/editar', [TarifaAdminController::class, 'edit'])
            ->name('tarifas.edit');

        Route::put('/tarifas/{tarifa}', [TarifaAdminController::class, 'update'])
            ->name('tarifas.update');

        Route::patch('/tarifas/{tarifa}/activar', [TarifaAdminController::class, 'activar'])
            ->name('tarifas.activar');

        Route::patch('/tarifas/{tarifa}/desactivar', [TarifaAdminController::class, 'desactivar'])
            ->name('tarifas.desactivar');
    });

/*
|--------------------------------------------------------------------------
| Rutas exclusivas del super administrador
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])
            ->name('dashboard');

        Route::get('/administradores/crear', [AdminUserController::class, 'create'])
            ->name('admins.create');

        Route::post('/administradores', [AdminUserController::class, 'store'])
            ->name('admins.store');

        Route::patch('/administradores/{user}/estado', [AdminUserController::class, 'toggleActivo'])
            ->name('admins.toggleActivo');
    });

/*
|--------------------------------------------------------------------------
| Rutas de autenticación
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
