<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\ReservaDisponibilidadService;
use App\Services\ReservaService;
use App\Services\SensoresApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SensorEstadoController extends Controller
{
    public function index(
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService,
        ReservaService $reservaService
    ): View
    {
        return view('monitoreo.sensores', [
            'estadoSensores' => $this->estadoConEspacios($sensoresApiService, $disponibilidadService, $reservaService),
        ]);
    }

    public function json(
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService,
        ReservaService $reservaService
    ): JsonResponse
    {
        return response()->json($this->estadoConEspacios($sensoresApiService, $disponibilidadService, $reservaService));
    }

    private function estadoConEspacios(
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService,
        ReservaService $reservaService
    ): array
    {
        $reservaService->procesarReservasAutomaticas();
        $estado = $sensoresApiService->obtenerEstado();

        if (!$estado['ok']) {
            return $estado;
        }

        $codigos = collect($estado['sensores'])
            ->pluck('codigo')
            ->filter()
            ->values();

        $sensoresLocales = Sensor::with(['espacio.vehiculoTipos.tarifas'])
            ->whereIn('codigo_sensor', $codigos->all())
            ->get()
            ->keyBy('codigo_sensor');

        $estado['sensores'] = collect($estado['sensores'])
            ->map(function (array $sensor) use ($sensoresLocales, $disponibilidadService) {
                $sensorLocal = $sensoresLocales->get($sensor['codigo']);
                $espacio = $sensorLocal?->espacio;
                $estadoTarjeta = $espacio
                    ? $disponibilidadService->estadoParaTarjeta($espacio, $sensor)
                    : [
                        'puede_reservar' => false,
                        'logicamente_disponible' => false,
                    ];

                $sensor['espacio'] = $espacio ? [
                    'id' => $espacio->id,
                    'codigo' => $espacio->codigo,
                    'descripcion' => $espacio->descripcion,
                    'estado_actual' => $espacio->estado_actual,
                    'vehiculos' => $espacio->vehiculoTipos
                        ->pluck('nombre')
                        ->values()
                        ->all(),
                    'logicamente_disponible' => $estadoTarjeta['logicamente_disponible'],
                ] : null;

                $sensor['puede_reservar'] = $estadoTarjeta['puede_reservar'];
                $sensor['estado_visual'] = $estadoTarjeta['estado_visual'] ?? $sensor['estado'];
                $sensor['estado_visual_texto'] = $estadoTarjeta['estado_texto'] ?? $sensor['estado_texto'];
                $sensor['bloqueo_motivo'] = $estadoTarjeta['bloqueo_motivo'] ?? null;

                $sensor['reserva_url'] = $sensor['puede_reservar']
                    ? route('reservas.create', $espacio)
                    : null;

                return $sensor;
            })
            ->values()
            ->all();

        $estado['resumen'] = [
            'total' => count($estado['sensores']),
            'libres' => collect($estado['sensores'])
                ->filter(fn (array $sensor) => ($sensor['ocupado'] ?? null) === false)
                ->count(),
            'ocupados' => collect($estado['sensores'])
                ->filter(fn (array $sensor) => ($sensor['ocupado'] ?? null) === true)
                ->count(),
            'sin_datos' => collect($estado['sensores'])
                ->filter(fn (array $sensor) => ($sensor['datos_disponibles'] ?? null) === false)
                ->count(),
            'sin_configuracion' => collect($estado['sensores'])
                ->filter(fn (array $sensor) => in_array(
                    $sensor['estado_visual'] ?? null,
                    ['sin_tipos', 'sin_tarifa'],
                    true
                ))
                ->count(),
        ];

        return $estado;
    }
}
