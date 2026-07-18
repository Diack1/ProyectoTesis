<?php

namespace App\Http\Controllers;
use App\Models\Espacio;
use App\Services\ReservaService;
use App\Models\Tarifa;
use App\Models\VehiculoTipo;
use App\Services\ReservaDisponibilidadService;
use App\Services\SensoresApiService;

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

    public function disponibilidad(
        ReservaService $reservaService,
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService
    )
    {
        $reservaService->procesarReservasAutomaticas();

        $espacios = Espacio::with(['vehiculoTipos.tarifas', 'sensor'])
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();

        $estadoSensores = $sensoresApiService->obtenerEstado();
        $sensoresPorCodigo = collect($estadoSensores['sensores'] ?? [])
            ->keyBy('codigo');
        $disponibilidadPorEspacio = $espacios
            ->mapWithKeys(function ($espacio) use ($sensoresPorCodigo, $disponibilidadService) {
                $sensorLocal = $espacio->sensor;
                $sensorExterno = $sensorLocal ? $sensoresPorCodigo->get($sensorLocal->codigo_sensor) : null;

                return [
                    $espacio->id => $disponibilidadService->estadoParaTarjeta($espacio, $sensorExterno),
                ];
            });

        $totalEspacios = $espacios->count();
        $espaciosLibres = $espacios
            ->filter(fn ($espacio) => ($disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null) === 'libre')
            ->count();
        $espaciosOcupados = $espacios
            ->filter(fn ($espacio) => ($disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null) === 'ocupado')
            ->count();
        $espaciosReservados = $espacios
            ->filter(fn ($espacio) => ($disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null) === 'reservado')
            ->count();
        $espaciosMantenimiento = $espacios
            ->filter(fn ($espacio) => ($disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null) === 'mantenimiento')
            ->count();
        $espaciosSinDatos = $espacios
            ->filter(fn ($espacio) => ($disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null) === 'sin_datos')
            ->count();
        $espaciosSinConfiguracion = $espacios
            ->filter(fn ($espacio) => in_array(
                $disponibilidadPorEspacio[$espacio->id]['estado_visual'] ?? null,
                ['sin_tipos', 'sin_tarifa'],
                true
            ))
            ->count();

        return view('public.disponibilidad', compact(
            'espacios',
            'totalEspacios',
            'espaciosLibres',
            'espaciosOcupados',
            'espaciosReservados',
            'espaciosMantenimiento',
            'espaciosSinDatos',
            'espaciosSinConfiguracion',
            'estadoSensores',
            'sensoresPorCodigo',
            'disponibilidadPorEspacio'
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
