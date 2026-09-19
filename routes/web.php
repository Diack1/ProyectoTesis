<?php

use App\Http\Controllers\Admin\ConfiguracionPagoController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PagoRevisionController;
use App\Http\Controllers\Admin\ReservaAdminController;
use App\Http\Controllers\Admin\TarifaAdminController;
use App\Http\Controllers\SensorEstadoController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\MonitoreoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\SuperAdmin\AdminUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas pÃºblicas
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicController::class, 'home'])
    ->name('public.home');

Route::get('/disponibilidad', [PublicController::class, 'disponibilidad'])
    ->name('public.disponibilidad');

Route::get('/disponibilidad/estado', [PublicController::class, 'estadoPlano'])->name('public.disponibilidad.estado');

Route::get('/tarifas', [PublicController::class, 'tarifas'])
    ->name('public.tarifas');

Route::get('/sensores/estado', [SensorEstadoController::class, 'index'])
    ->name('sensores.estado')->middleware(['auth', 'role:admin,super_admin,operador']);

Route::get('/sensores/estado/json', [SensorEstadoController::class, 'json'])
    ->name('sensores.estado.json')->middleware(['auth', 'role:admin,super_admin,operador']);

/*
|--------------------------------------------------------------------------
| Rutas de usuario autenticado
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'super_admin') {
            return redirect()->route('superadmin.dashboard');
        }

        if (auth()->user()->tieneRol('admin', 'operador')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('reservas.index');
    })->name('dashboard');

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

    Route::post('/reservas/{reserva}/pago', [PagoController::class, 'enviar'])->name('pagos.enviar');

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

Route::middleware(['auth', 'role:admin,super_admin,operador'])
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
            ->name('espacios.index')->middleware('role:admin,super_admin');

        Route::get('/espacios/crear', [EspacioController::class, 'create'])
            ->name('espacios.create')->middleware('role:admin,super_admin');

        Route::post('/espacios', [EspacioController::class, 'store'])
            ->name('espacios.store')->middleware('role:admin,super_admin');

        Route::get('/espacios/{espacio}/editar', [EspacioController::class, 'edit'])
            ->name('espacios.edit')->middleware('role:admin,super_admin');

        Route::put('/espacios/{espacio}', [EspacioController::class, 'update'])
            ->name('espacios.update')->middleware('role:admin,super_admin');

        Route::delete('/espacios/{espacio}', [EspacioController::class, 'destroy'])
            ->name('espacios.destroy')->middleware('role:admin,super_admin');

        Route::get('/reportes', [ReporteController::class, 'index'])
            ->name('reportes.index');

        Route::get('/reportes/exportar-csv', [ReporteController::class, 'exportarCsv'])
            ->name('reportes.exportarCsv');

        Route::get('/reservas', [ReservaAdminController::class, 'index'])
            ->name('reservas.index');

        Route::post('/reembolsos/{reembolso}/aprobar', [ReservaAdminController::class, 'aprobarReembolso'])
            ->name('reembolsos.aprobar')->middleware('role:admin,super_admin');

        Route::post('/reembolsos/{reembolso}/rechazar', [ReservaAdminController::class, 'rechazarReembolso'])
            ->name('reembolsos.rechazar')->middleware('role:admin,super_admin');

        Route::get('/tarifas', [TarifaAdminController::class, 'index'])
            ->name('tarifas.index')->middleware('role:admin,super_admin');

        Route::get('/tarifas/crear', [TarifaAdminController::class, 'create'])
            ->name('tarifas.create')->middleware('role:admin,super_admin');

        Route::post('/tarifas', [TarifaAdminController::class, 'store'])
            ->name('tarifas.store')->middleware('role:admin,super_admin');

        Route::get('/tarifas/{tarifa}/editar', [TarifaAdminController::class, 'edit'])
            ->name('tarifas.edit')->middleware('role:admin,super_admin');

        Route::put('/tarifas/{tarifa}', [TarifaAdminController::class, 'update'])
            ->name('tarifas.update')->middleware('role:admin,super_admin');

        Route::patch('/tarifas/{tarifa}/activar', [TarifaAdminController::class, 'activar'])
            ->name('tarifas.activar')->middleware('role:admin,super_admin');

        Route::patch('/tarifas/{tarifa}/desactivar', [TarifaAdminController::class, 'desactivar'])
            ->name('tarifas.desactivar')->middleware('role:admin,super_admin');
    });

/*
|--------------------------------------------------------------------------
| Rutas exclusivas del super administrador
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,super_admin'])
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
| Rutas de autenticaciÃ³n
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';

Route::middleware(['auth', 'role:user,admin,super_admin,operador'])->group(function () {
    Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])->name('pagos.comprobante');
    Route::get('/cobro/qr/{metodo}', [ConfiguracionPagoController::class, 'qr'])->name('pagos.qr');
});
Route::middleware(['auth', 'role:admin,super_admin,operador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pagos', [PagoRevisionController::class, 'index'])->name('pagos.index');
    Route::get('/pagos/pendientes', [PagoRevisionController::class, 'pendientes'])->name('pagos.pendientes');
    Route::post('/pagos/{pago}/revision', [PagoRevisionController::class, 'revisar'])->name('pagos.revisar');
});
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/configuracion-pagos', [ConfiguracionPagoController::class, 'edit'])->name('pagos.configuracion');
    Route::put('/configuracion-pagos', [ConfiguracionPagoController::class, 'update'])->name('pagos.configuracion.update');
});

Route::get('/dashboard', function (Request $request) {
    return redirect()->route(match ($request->user()->role) {
        'super_admin' => 'superadmin.dashboard',
        'admin', 'operador' => 'admin.dashboard',
        default => 'reservas.index',
    });
})->middleware(['auth', 'role:user,admin,super_admin,operador'])->name('dashboard');

Route::middleware(['auth', 'role:admin,super_admin,operador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/estadias', [\App\Http\Controllers\Admin\EstadiaController::class, 'index'])->name('estadias.index');
    Route::get('/estadias/ingreso', [\App\Http\Controllers\Admin\EstadiaController::class, 'create'])->name('estadias.create');
    Route::post('/estadias', [\App\Http\Controllers\Admin\EstadiaController::class, 'store'])->name('estadias.store');
    Route::get('/estadias/{estadia}', [\App\Http\Controllers\Admin\EstadiaController::class, 'show'])->name('estadias.show');
    Route::get('/estadias/{estadia}/ticket', [\App\Http\Controllers\Admin\EstadiaController::class, 'ticket'])->name('estadias.ticket');
    Route::post('/estadias/{estadia}/salida', [\App\Http\Controllers\Admin\EstadiaController::class, 'salida'])->name('estadias.salida');
    Route::post('/estadias/{estadia}/inicio-manual', [\App\Http\Controllers\Admin\EstadiaController::class, 'inicioManual'])->name('estadias.inicio-manual');
});

Route::middleware(['auth','role:admin,super_admin,operador'])->prefix('admin')->name('admin.')->group(function () {
 Route::get('/placas', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'index'])->name('placas.index');
 Route::post('/placas/camara', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'camara'])->middleware('throttle:40,1')->name('placas.camara');
 Route::post('/placas/cliente', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'cliente'])->middleware('throttle:40,1')->name('placas.cliente');
 Route::get('/clientes-vehiculos', [\App\Http\Controllers\Admin\ClienteVehiculoController::class, 'index'])->name('clientes-vehiculos.index');
 Route::post('/clientes-vehiculos', [\App\Http\Controllers\Admin\ClienteVehiculoController::class, 'guardar'])->name('clientes-vehiculos.store');
 Route::put('/clientes-vehiculos/{vehiculo}', [\App\Http\Controllers\Admin\ClienteVehiculoController::class, 'guardar'])->name('clientes-vehiculos.update');
 Route::post('/placas/analizar', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'analizar'])->middleware('throttle:10,1')->name('placas.analizar');
 Route::post('/placas/confirmar', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'confirmar'])->name('placas.confirmar');
 Route::delete('/placas', [\App\Http\Controllers\Admin\PlacaVisionController::class, 'limpiar'])->name('placas.limpiar');
 Route::get('/sensores-iot',[\App\Http\Controllers\Admin\SensorIotController::class,'index'])->name('sensores.index');
 Route::put('/sensores-iot/{sensor}',[\App\Http\Controllers\Admin\SensorIotController::class,'update'])->middleware('role:admin,super_admin')->name('sensores.update');
 Route::post('/sensores-iot/{sensor}/credencial',[\App\Http\Controllers\Admin\SensorIotController::class,'token'])->middleware('role:admin,super_admin')->name('sensores.token');
});
