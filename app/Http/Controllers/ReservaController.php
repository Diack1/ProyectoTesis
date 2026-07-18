<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use App\Models\Espacio;
use App\Models\Tarifa;
use Carbon\Carbon;
use App\Services\ReservaService;
use App\Models\Reembolso;
use App\Models\VehiculoTipo;
use App\Services\TarifaService;
use RuntimeException;

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

    //SOLICITUD CREAR

    public function create(Espacio $espacio)
    {
        if (!$espacio->activo || $espacio->estado_actual !== 'libre') {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'El espacio seleccionado ya no está disponible para reserva.');
        }

        $vehiculoTipos = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->orderBy('nombre')
            ->get();

        if ($vehiculoTipos->isEmpty()) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'No existen tipos de vehículo activos.');
        }


        $fechaActual = now('America/Lima')->format('Y-m-d');
        $horaActual = now('America/Lima')->addMinutes(15)->format('H:i');

        return view('reservas.create', compact(
            'espacio',
            'vehiculoTipos',
            'fechaActual',
            'horaActual'
        ));
    }


    //SOLICITUD CONFIRMAR

    public function confirmar(Request $request, Espacio $espacio, TarifaService $tarifaService)
    {
        if (!$espacio->activo || $espacio->estado_actual !== 'libre') {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'El espacio seleccionado ya no está disponible para reserva.');
        }

        $hoy = now('America/Lima')->format('Y-m-d');
        $manana = now('America/Lima')->addDay()->format('Y-m-d');

        $request->validate([
            'vehiculo_tipo_id' => 'required|exists:vehiculo_tipos,id',
            'fecha_reserva' => [
                'required',
                'date',
                'after_or_equal:' . $hoy,
                'before_or_equal:' . $manana,
            ],
            'hora_inicio' => 'required|date_format:H:i',
            'duracion_minutos' => 'required|integer|in:60,120,180,240',
        ], [
            'fecha_reserva.after_or_equal' => 'No puedes reservar en fechas pasadas.',
            'fecha_reserva.before_or_equal' => 'Solo puedes reservar para hoy o como máximo mañana.',
            'hora_inicio.date_format' => 'La hora de ingreso debe tener un formato válido.',
        ]);

        $vehiculoTipo = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->where('vehiculo_tipos.id', $request->vehiculo_tipo_id)
            ->first();

        if (!$vehiculoTipo) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'vehiculo_tipo_id' => 'El tipo de vehículo seleccionado no está permitido para este espacio.',
                ])
                ->withInput();
        }

        $fechaReserva = $request->fecha_reserva;
        $horaInicio = $request->hora_inicio;
        $duracionMinutos = (int) $request->duracion_minutos;

        $fechaHoraInicio = Carbon::createFromFormat(
            'Y-m-d H:i',
            $fechaReserva . ' ' . $horaInicio,
            'America/Lima'
        );

        $minimoPermitido = now('America/Lima')->addMinutes(15)->startOfMinute();

        if ($fechaHoraInicio->lt($minimoPermitido)) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'hora_inicio' => 'La reserva debe realizarse con al menos 15 minutos de anticipación.',
                ])
                ->withInput();
        }

        $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes($duracionMinutos);

        try {
            $calculo = $tarifaService->calcularMonto(
                $vehiculoTipo,
                $fechaHoraInicio,
                $duracionMinutos
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'tarifa' => $e->getMessage(),
                ])
                ->withInput();
        }

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


    //SOLICITUD STORE

    public function store(Request $request, Espacio $espacio, TarifaService $tarifaService)
    {
        if (!$espacio->activo || $espacio->estado_actual !== 'libre') {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'El espacio seleccionado ya no está disponible para reserva.');
        }

        $hoy = now('America/Lima')->format('Y-m-d');
        $manana = now('America/Lima')->addDay()->format('Y-m-d');

        $request->validate([
            'vehiculo_tipo_id' => 'required|exists:vehiculo_tipos,id',
            'fecha_reserva' => [
                'required',
                'date',
                'after_or_equal:' . $hoy,
                'before_or_equal:' . $manana,
            ],
            'hora_inicio' => 'required|date_format:H:i',
            'duracion_minutos' => 'required|integer|in:60,120,180,240',
        ], [
            'fecha_reserva.after_or_equal' => 'No puedes reservar en fechas pasadas.',
            'fecha_reserva.before_or_equal' => 'Solo puedes reservar para hoy o como máximo mañana.',
            'hora_inicio.date_format' => 'La hora de ingreso debe tener un formato válido.',
        ]);

        $vehiculoTipo = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->where('vehiculo_tipos.id', $request->vehiculo_tipo_id)
            ->first();

        if (!$vehiculoTipo) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'vehiculo_tipo_id' => 'El tipo de vehículo seleccionado no está permitido para este espacio.',
                ])
                ->withInput();
        }

        $fechaReserva = $request->fecha_reserva;
        $horaInicio = $request->hora_inicio;
        $duracionMinutos = (int) $request->duracion_minutos;

        $fechaHoraInicio = Carbon::createFromFormat(
            'Y-m-d H:i',
            $fechaReserva . ' ' . $horaInicio,
            'America/Lima'
        );

        $minimoPermitido = now('America/Lima')->addMinutes(15)->startOfMinute();

        if ($fechaHoraInicio->lt($minimoPermitido)) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'hora_inicio' => 'La reserva debe realizarse con al menos 15 minutos de anticipación.',
                ])
                ->withInput();
        }

        $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes($duracionMinutos);

        try {
            $calculo = $tarifaService->calcularMonto(
                $vehiculoTipo,
                $fechaHoraInicio,
                $duracionMinutos
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'tarifa' => $e->getMessage(),
                ])
                ->withInput();
        }

        $tarifa = $calculo['tarifa'];
        $montoTotal = $calculo['monto_total'];

        $reserva = DB::transaction(function () use (
            $espacio,
            $vehiculoTipo,
            $tarifa,
            $calculo,
            $fechaReserva,
            $fechaHoraInicio,
            $fechaHoraFin,
            $duracionMinutos,
            $montoTotal
        ) {
            $codigoReserva = 'RES-' . now('America/Lima')->format('YmdHis') . '-' . Auth::id();

            $reserva = Reserva::create([
                'user_id' => Auth::id(),
                'espacio_id' => $espacio->id,
                'vehiculo_tipo_id' => $vehiculoTipo->id,
                'tarifa_id' => $tarifa->id,

                'tipo_vehiculo_nombre' => $vehiculoTipo->nombre,
                'tarifa_nombre' => $tarifa->nombre,
                'tipo_tarifa' => $tarifa->tipo_tarifa,

                'codigo_reserva' => $codigoReserva,
                'fecha_reserva' => $fechaReserva,
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
                'observacion' => 'Reserva generada desde la plataforma pública.',
            ]);

            Pago::create([
                'reserva_id' => $reserva->id,
                'user_id' => Auth::id(),
                'codigo_pago' => 'PAG-' . now('America/Lima')->format('YmdHis') . '-' . Auth::id(),
                'metodo_pago' => 'simulado',
                'monto' => $montoTotal,
                'estado' => 'pendiente',
            ]);

            $espacio->update([
                'estado_actual' => 'reservado',
            ]);

            return $reserva;
        });

        return redirect()
            ->route('pagos.show', $reserva)
            ->with('success', 'Reserva generada correctamente. Tienes 10 minutos para realizar el pago.');
    }


    //SOLICITUD CANCELAR
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
            $reserva->update([
                'estado' => 'cancelada',
                'cancelado_at' => now(),
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

    //SOLICITUD REEMBOLSO
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
                ->with('error', 'No se encontró un pago aprobado para esta reserva.');
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
                'motivo' => 'Solicitud de cancelación realizada por el usuario.',
                'estado' => 'solicitado',
                'solicitado_at' => now(),
            ]);

            $reserva->update([
                'estado' => 'reembolso_solicitado',
                'cancelado_at' => now(),
            ]);

            if ($reserva->espacio && $reserva->espacio->estado_actual === 'reservado') {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Solicitud de reembolso registrada correctamente. La administración revisará tu caso.');
    }
}
