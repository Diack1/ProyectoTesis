<?php

namespace App\Http\Controllers;
use App\Models\Espacio;
use App\Services\ReservaService;
use App\Models\Tarifa;
use App\Models\VehiculoTipo;

class PublicController extends Controller
{
    public function home(ReservaService $reservaService)
    {
        $reservaService->procesarReservasAutomaticas();

        $espacios = Espacio::with('vehiculoTipos')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();

        $totalEspacios = $espacios->count();
        $espaciosLibres = $espacios->where('estado_actual', 'libre')->count();
        $espaciosOcupados = $espacios->where('estado_actual', 'ocupado')->count();
        $espaciosReservados = $espacios->where('estado_actual', 'reservado')->count();
        $espaciosMantenimiento = $espacios->where('estado_actual', 'mantenimiento')->count();

        return view('public.home', compact(
            'espacios',
            'totalEspacios',
            'espaciosLibres',
            'espaciosOcupados',
            'espaciosReservados',
            'espaciosMantenimiento'
        ));
    }

    public function disponibilidad(ReservaService $reservaService)
    {
        $reservaService->procesarReservasAutomaticas();

        $espacios = Espacio::with('vehiculoTipos')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();

        $totalEspacios = $espacios->count();
        $espaciosLibres = $espacios->where('estado_actual', 'libre')->count();
        $espaciosOcupados = $espacios->where('estado_actual', 'ocupado')->count();
        $espaciosReservados = $espacios->where('estado_actual', 'reservado')->count();
        $espaciosMantenimiento = $espacios->where('estado_actual', 'mantenimiento')->count();

        return view('public.disponibilidad', compact(
            'espacios',
            'totalEspacios',
            'espaciosLibres',
            'espaciosOcupados',
            'espaciosReservados',
            'espaciosMantenimiento'
        ));
    }

    public function tarifas()
    {
        $vehiculoTipos = VehiculoTipo::with(['tarifas' => function ($query) {
            $query->where('activo', true)
                ->orderByDesc('prioridad')
                ->orderBy('tipo_tarifa');
        }])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('public.tarifas', compact('vehiculoTipos'));
    }
}