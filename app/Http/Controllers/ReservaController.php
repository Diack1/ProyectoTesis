<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservaRequest;
use App\Models\Espacio;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\Reserva;
use App\Services\ReservaDisponibilidadService;
use App\Services\ReservaService;
use App\Services\SensoresApiService;
use App\Services\TarifaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReservaController extends Controller
{
    public function index(ReservaService $reservaService)
    {
        $reservaService->procesarReservasAutomaticas();

        $reservas = Reserva::with('espacio')
            ->where('user_id', Auth::id())
            ->orderBy('fecha_reserva', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->paginate(10);

        return view('reservas.index', compact('reservas'));
    }

    public function create(
        Espacio $espacio,
        ReservaService $reservaService,
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService
    ) {
        $reservaService->procesarReservasAutomaticas();

        $espacio->load(['sensor', 'vehiculoTipos.tarifas']);
        $disponibilidad = $disponibilidadService->verificarEspacioParaInicio($espacio, $sensoresApiService);

        if (!$disponibilidad['disponible']) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', $disponibilidad['message']);
        }

        $tiposActivos = $disponibilidadService->tiposPermitidosActivos($espacio);

        if ($tiposActivos->isEmpty()) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'No existen tipos de vehiculo activos para este espacio.');
        }

        $vehiculoTipos = $disponibilidadService->tiposPermitidosConTarifa($espacio)
            ->sortBy('nombre')
            ->values();

        if ($vehiculoTipos->isEmpty()) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'No existe una tarifa activa para los tipos de vehiculo de este espacio.');
        }

        $fechaActual = now('America/Lima')->format('Y-m-d');
        $horaActual = now('America/Lima')->addMinutes(15)->format('H:i');
        $sensorActual = $disponibilidad['sensor'] ?? null;
        $tarifasFrontend = $this->tarifasParaFormulario($vehiculoTipos);

        return view('reservas.create', compact(
            'espacio',
            'vehiculoTipos',
            'fechaActual',
            'horaActual',
            'sensorActual',
            'tarifasFrontend'
        ));
    }

    public function confirmar(
        StoreReservaRequest $request,
        Espacio $espacio,
        TarifaService $tarifaService,
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService,
        ReservaService $reservaService
    ) {
        $reservaService->procesarReservasAutomaticas();

        $espacio->load(['sensor', 'vehiculoTipos.tarifas']);
        $fechaHoraInicio = $request->fechaHoraInicio();
        $fechaHoraFin = $request->fechaHoraFin();
        $duracionMinutos = $request->duracionMinutos();

        $disponibilidad = $disponibilidadService->verificarEspacioParaHorario(
            $espacio,
            $fechaHoraInicio,
            $fechaHoraFin,
            $sensoresApiService
        );

        if (!$disponibilidad['disponible']) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withInput()
                ->with('error', $disponibilidad['message']);
        }

        $vehiculoTipo = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->where('vehiculo_tipos.id', $request->integer('vehiculo_tipo_id'))
            ->first();

        if (!$vehiculoTipo) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'vehiculo_tipo_id' => 'El tipo de vehiculo no esta permitido para este espacio.',
                ])
                ->withInput();
        }

        try {
            $calculo = $tarifaService->calcularMonto(
                $vehiculoTipo,
                $fechaHoraInicio,
                $duracionMinutos
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'tarifa' => $exception->getMessage(),
                ])
                ->withInput();
        }

        $fechaReserva = $request->input('fecha_reserva');
        $horaInicio = $request->input('hora_inicio');

        return response()
            ->view('reservas.confirmacion', compact(
                'espacio',
                'vehiculoTipo',
                'fechaReserva',
                'horaInicio',
                'duracionMinutos',
                'fechaHoraInicio',
                'fechaHoraFin',
                'calculo'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function store(
        StoreReservaRequest $request,
        Espacio $espacio,
        TarifaService $tarifaService,
        SensoresApiService $sensoresApiService,
        ReservaDisponibilidadService $disponibilidadService
    ) {
        try {
            $reserva = DB::transaction(function () use (
                $request,
                $espacio,
                $tarifaService,
                $sensoresApiService,
                $disponibilidadService
            ) {
                $espacioBloqueado = Espacio::with(['sensor', 'vehiculoTipos'])
                    ->whereKey($espacio->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $fechaHoraInicio = $request->fechaHoraInicio();
                $fechaHoraFin = $request->fechaHoraFin();
                $duracionMinutos = $request->duracionMinutos();

                $disponibilidad = $disponibilidadService->verificarEspacioParaHorario(
                    $espacioBloqueado,
                    $fechaHoraInicio,
                    $fechaHoraFin,
                    $sensoresApiService,
                    true
                );

                if (!$disponibilidad['disponible']) {
                    throw new RuntimeException($disponibilidad['message']);
                }

                $vehiculoTipo = $espacioBloqueado->vehiculoTipos()
                    ->where('vehiculo_tipos.activo', true)
                    ->where('vehiculo_tipos.id', $request->integer('vehiculo_tipo_id'))
                    ->first();

                if (!$vehiculoTipo) {
                    throw new RuntimeException('El tipo de vehiculo no esta permitido para este espacio.');
                }

                $calculo = $tarifaService->calcularMonto(
                    $vehiculoTipo,
                    $fechaHoraInicio,
                    $duracionMinutos
                );

                $tarifa = $calculo['tarifa'];
                $montoTotal = $calculo['monto_total'];
                $codigoSufijo = Auth::id() . '-' . Str::upper(Str::random(6));
                $codigoReserva = 'RES-' . now('America/Lima')->format('YmdHis') . '-' . $codigoSufijo;

                $reserva = Reserva::create([
                    'user_id' => Auth::id(),
                    'espacio_id' => $espacioBloqueado->id,
                    'vehiculo_tipo_id' => $vehiculoTipo->id,
                    'tarifa_id' => $tarifa->id,
                    'tipo_vehiculo_nombre' => $vehiculoTipo->nombre,
                    'tarifa_nombre' => $tarifa->nombre,
                    'tipo_tarifa' => $tarifa->tipo_tarifa,
                    'codigo_reserva' => $codigoReserva,
                    'fecha_reserva' => $request->input('fecha_reserva'),
                    'hora_inicio' => $fechaHoraInicio->format('H:i:s'),
                    'hora_fin' => $fechaHoraFin->format('H:i:s'),
                    'duracion_minutos' => $duracionMinutos,
                    'tarifa_hora' => $calculo['tarifa_hora'],
                    'monto_total' => $montoTotal,
                    'tolerancia_minutos' => $calculo['tolerancia_minutos'],
                    'penalidad_por_fraccion' => $calculo['penalidad_por_fraccion'],
                    'monto_penalidad' => 0,
                    'estado' => 'pendiente_pago',
                    'expires_at' => now('America/Lima')->addMinutes(10),
                    'observacion' => 'Reserva generada desde la plataforma publica.',
                ]);

                Pago::create([
                    'reserva_id' => $reserva->id,
                    'user_id' => Auth::id(),
                    'codigo_pago' => 'PAG-' . now('America/Lima')->format('YmdHis') . '-' . $codigoSufijo,
                    'metodo_pago' => 'simulado',
                    'monto' => $montoTotal,
                    'estado' => 'pendiente',
                ]);

                $espacioBloqueado->update([
                    'estado_actual' => 'reservado',
                ]);

                return $reserva;
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Error al registrar reserva.', [
                'message' => $exception->getMessage(),
                'espacio_id' => $espacio->id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('reservas.create', $espacio)
                ->withInput()
                ->with('error', 'No se pudo registrar la reserva. Intentalo nuevamente.');
        }

        return redirect()
            ->route('pagos.show', $reserva)
            ->with('success', 'Reserva registrada correctamente. Tienes 10 minutos para realizar el pago.');
    }

    public function cancelar(Reserva $reserva)
    {
        if ($reserva->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para cancelar esta reserva.');
        }

        if ($reserva->estado !== 'pendiente_pago') {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Solo puedes cancelar reservas pendientes de pago.');
        }

        DB::transaction(function () use ($reserva) {
            $reserva = Reserva::with('espacio')
                ->whereKey($reserva->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->estado !== 'pendiente_pago') {
                return;
            }

            $reserva->update([
                'estado' => 'cancelada',
                'cancelado_at' => now('America/Lima'),
            ]);

            $reserva->pagos()
                ->where('estado', 'pendiente')
                ->update([
                    'estado' => 'cancelado',
                ]);

            if ($reserva->espacio) {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Reserva cancelada correctamente. El espacio fue liberado.');
    }

    public function solicitarReembolso(Reserva $reserva)
    {
        if ($reserva->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para solicitar reembolso de esta reserva.');
        }

        if ($reserva->estado !== 'confirmada') {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Solo puedes solicitar reembolso de reservas confirmadas.');
        }

        $pago = $reserva->pagos()
            ->where('estado', 'aprobado')
            ->latest()
            ->first();

        if (!$pago) {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'No se encontro un pago aprobado para esta reserva.');
        }

        $reembolsoExistente = Reembolso::where('reserva_id', $reserva->id)
            ->whereIn('estado', ['solicitado', 'aprobado', 'procesado'])
            ->first();

        if ($reembolsoExistente) {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Esta reserva ya tiene una solicitud de reembolso registrada.');
        }

        DB::transaction(function () use ($reserva, $pago) {
            Reembolso::create([
                'reserva_id' => $reserva->id,
                'pago_id' => $pago->id,
                'user_id' => Auth::id(),
                'monto' => $pago->monto,
                'motivo' => 'Solicitud de cancelacion realizada por el usuario.',
                'estado' => 'solicitado',
                'solicitado_at' => now('America/Lima'),
            ]);

            $reserva->update([
                'estado' => 'reembolso_solicitado',
                'cancelado_at' => now('America/Lima'),
            ]);

            if ($reserva->espacio && $reserva->espacio->estado_actual === 'reservado') {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Solicitud de reembolso registrada correctamente. La administracion revisara tu caso.');
    }

    private function tarifasParaFormulario($vehiculoTipos): array
    {
        return $vehiculoTipos
            ->mapWithKeys(function ($tipo) {
                $tarifas = $tipo->tarifas
                    ->where('activo', true)
                    ->sortByDesc('prioridad')
                    ->values()
                    ->map(fn ($tarifa) => [
                        'id' => $tarifa->id,
                        'nombre' => $tarifa->nombre,
                        'tipo_tarifa' => $tarifa->tipo_tarifa,
                        'monto_base' => (float) $tarifa->monto_base,
                        'monto_por_hora' => (float) $tarifa->monto_por_hora,
                        'monto_por_fraccion' => $tarifa->monto_por_fraccion !== null ? (float) $tarifa->monto_por_fraccion : null,
                        'minutos_fraccion' => $tarifa->minutos_fraccion !== null ? (int) $tarifa->minutos_fraccion : null,
                        'tiempo_minimo_minutos' => (int) $tarifa->tiempo_minimo_minutos,
                        'tolerancia_minutos' => (int) $tarifa->tolerancia_minutos,
                        'penalidad_por_fraccion' => (float) $tarifa->penalidad_por_fraccion,
                        'hora_inicio' => $tarifa->hora_inicio ? substr($tarifa->hora_inicio, 0, 8) : null,
                        'hora_fin' => $tarifa->hora_fin ? substr($tarifa->hora_fin, 0, 8) : null,
                        'prioridad' => (int) $tarifa->prioridad,
                    ]);

                return [$tipo->id => $tarifas->all()];
            })
            ->all();
    }
}
