<?php

namespace App\Http\Controllers;
use App\Models\Espacio;
use App\Services\ReservaService;
use App\Models\VehiculoTipo;
use App\Services\ReservaDisponibilidadService;

class PublicController extends Controller
{
    public function home(ReservaService $reservaService)
    {
        $reservaService->procesarReservasAutomaticas();

        $espacios = Espacio::conEstadoOperativo()->with(['vehiculoTipos', 'sensor'])
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

    private function espaciosPlano()
    {
        return Espacio::conEstadoOperativo()->with(['vehiculoTipos.tarifas', 'sensor'])->where('activo', true)->orderBy('codigo')->get();
    }

    public function disponibilidad(ReservaService $reservas, ReservaDisponibilidadService $disponibilidad)
    {
        $reservas->procesarReservasAutomaticas();
        $espacios = $this->espaciosPlano();
        $disponibilidadPorEspacio = $espacios->mapWithKeys(fn ($e) => [$e->id => $disponibilidad->estadoParaTarjeta($e)]);
        return response()->view('public.disponibilidad', compact('espacios', 'disponibilidadPorEspacio'))->header('Cache-Control', 'no-store');
    }

    public function estadoPlano(ReservaService $reservas, ReservaDisponibilidadService $disponibilidad)
    {
        $reservas->procesarReservasAutomaticas();
        return response()->json(['espacios' => $this->espaciosPlano()->map(fn ($e) => [
            'id' => $e->id, 'codigo' => $e->codigo,
            ...$disponibilidad->estadoParaTarjeta($e),
        ])])->header('Cache-Control', 'no-store');
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
