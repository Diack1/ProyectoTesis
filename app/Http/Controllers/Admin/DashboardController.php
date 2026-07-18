<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Sensor;
use App\Models\RegistroOcupacion;
use App\Models\User;
use App\Models\Reserva;
use App\Models\Pago;
use App\Models\Reembolso;

class DashboardController extends Controller
{
    public function index()
    {
        // Espacios
        $totalEspacios = Espacio::count();
        $espaciosLibres = Espacio::where('estado_actual', 'libre')->count();
        $espaciosOcupados = Espacio::where('estado_actual', 'ocupado')->count();
        $espaciosReservados = Espacio::where('estado_actual', 'reservado')->count();
        $espaciosMantenimiento = Espacio::where('estado_actual', 'mantenimiento')->count();

        // Sensores
        $totalSensores = Sensor::count();
        $sensoresActivos = Sensor::where('estado', 'activo')->count();
        $sensoresInactivos = Sensor::where('estado', 'inactivo')->count();

        // Registros
        $totalRegistros = RegistroOcupacion::count();

        // Usuarios
        $totalUsuarios = User::where('role', 'user')->count();
        $totalAdmins = User::where('role', 'admin')->count();

        // Reservas
        $totalReservas = Reserva::count();
        $reservasPendientesPago = Reserva::where('estado', 'pendiente_pago')->count();
        $reservasConfirmadas = Reserva::where('estado', 'confirmada')->count();
        $reservasCanceladas = Reserva::where('estado', 'cancelada')->count();
        $reservasExpiradas = Reserva::where('estado', 'expirada')->count();
        $reservasFinalizadas = Reserva::where('estado', 'finalizada')->count();

        // Pagos
        $pagosPendientes = Pago::where('estado', 'pendiente')->count();
        $pagosAprobados = Pago::where('estado', 'aprobado')->count();
        $pagosCancelados = Pago::where('estado', 'cancelado')->count();
        $pagosReembolsados = Pago::where('estado', 'reembolsado')->count();

        // Reembolsos
        $reembolsosSolicitados = Reembolso::where('estado', 'solicitado')->count();
        $reembolsosAprobados = Reembolso::where('estado', 'aprobado')->count();
        $reembolsosRechazados = Reembolso::where('estado', 'rechazado')->count();

        $ultimasReservas = Reserva::with(['usuario', 'espacio', 'vehiculoTipo', 'tarifa'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalEspacios',
            'espaciosLibres',
            'espaciosOcupados',
            'espaciosReservados',
            'espaciosMantenimiento',

            'totalSensores',
            'sensoresActivos',
            'sensoresInactivos',

            'totalRegistros',

            'totalUsuarios',
            'totalAdmins',

            'totalReservas',
            'reservasPendientesPago',
            'reservasConfirmadas',
            'reservasCanceladas',
            'reservasExpiradas',
            'reservasFinalizadas',

            'pagosPendientes',
            'pagosAprobados',
            'pagosCancelados',
            'pagosReembolsados',

            'reembolsosSolicitados',
            'reembolsosAprobados',
            'reembolsosRechazados',

            'ultimasReservas'
        ));
    }
}
