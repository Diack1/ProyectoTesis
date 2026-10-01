<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionPago;
use App\Models\Espacio;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\Reserva;
use App\Services\ReservaDisponibilidadService;
use App\Services\ReservaService;
use App\Services\TarifaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class ReservaController extends Controller
{
    private function validarPlaca(Request $request): string
    {
        $request->validate(['placa' => ['required', 'string', 'max:20']]);
        $placa = strtoupper(preg_replace('/[\s-]+/', '', $request->input('placa')));
        if (! preg_match('/\A[A-Z0-9]{5,10}\z/', $placa)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'placa' => 'Ingresa una placa de 5 a 10 letras y números, por ejemplo ABC-123.',
            ]);
        }
        return $placa;
    }

    public function index(ReservaService $reservaService, Request $request)
    {
        $reservaService->procesarReservasAutomaticas();

        $filtro = $request->validate(['estado' => 'nullable|in:todas,activas,finalizadas,canceladas'])['estado'] ?? 'todas';
        $base = Reserva::where('user_id', Auth::id());
        $groups = [
            'activas' => ['pendiente', 'pendiente_pago', 'confirmada', 'reembolso_solicitado'],
            'finalizadas' => ['finalizada', 'reembolso_aprobado', 'reembolso_rechazado'],
            'canceladas' => ['cancelada', 'expirada'],
        ];
        $conteos = ['todas' => (clone $base)->count()];
        foreach ($groups as $key => $states) $conteos[$key] = (clone $base)->whereIn('estado', $states)->count();
        $reservas = $base->with('espacio','estadia')
            ->when($filtro !== 'todas', fn ($q) => $q->whereIn('estado', $groups[$filtro]))
            ->orderByRaw("CASE WHEN estado IN ('pendiente_pago', 'confirmada', 'reembolso_solicitado') THEN 0 ELSE 1 END")
            ->orderBy('fecha_reserva', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->paginate(10)->withQueryString();

        return view('reservas.index', compact('reservas', 'conteos', 'filtro'));
    }

    // SOLICITUD CREAR

    public function create(Espacio $espacio)
    {
        if (! app(ReservaDisponibilidadService::class)->estadoParaTarjeta($espacio)['puede_reservar']) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'El espacio seleccionado ya no está disponible.');
        }

        $disponibilidadService = app(ReservaDisponibilidadService::class);
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
        $sensorActual = null;

        $tarifasIniciales = \App\Services\TarifaResumen::opciones($vehiculoTipos);
        return view('reservas.create', compact(
            'tarifasIniciales',
            'espacio',
            'vehiculoTipos',
            'fechaActual',
            'horaActual',
            'sensorActual'
        ));
    }

    // SOLICITUD CONFIRMAR

    public function confirmar(Request $request, Espacio $espacio, TarifaService $tarifaService)
    {
        if ($request->isMethod('get')) {
            $draft = $request->session()->get('reservation_review.'.$espacio->id);
            if (!$draft || $draft['user_id'] !== $request->user()->id || $draft['expires_at'] < now()->timestamp) {
                return redirect()->route('reservas.create', $espacio)->with('error', 'Vuelve a ingresar los datos para revisar tu reserva.');
            }
            $request->merge($draft['data']);
        }
        $placa = $this->validarPlaca($request);
        if (! app(ReservaDisponibilidadService::class)->estadoParaTarjeta($espacio)['puede_reservar']) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withInput()
                ->with('error', 'El espacio seleccionado ya no está disponible.');
        }

        $request->validate(['vehiculo_tipo_id' => 'required|exists:vehiculo_tipos,id',
            'duracion_minutos' => 'required|integer|in:60,120,180,240']);

        $vehiculoTipo = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->where('vehiculo_tipos.id', $request->integer('vehiculo_tipo_id'))
            ->first();

        if (! $vehiculoTipo) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'vehiculo_tipo_id' => 'El tipo de vehiculo no esta permitido para este espacio.',
                ])
                ->withInput();
        }

        $fechaHoraInicio = now('America/Lima')->startOfMinute();
        $fechaReserva = $fechaHoraInicio->toDateString();
        $horaInicio = $fechaHoraInicio->format('H:i');
        $duracionMinutos = (int) $request->duracion_minutos;

        $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes($duracionMinutos);

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


        if ($request->isMethod('post')) {
            $request->session()->put('reservation_review.'.$espacio->id, [
                'user_id' => $request->user()->id, 'expires_at' => now()->addMinutes(30)->timestamp,
                'data' => ['placa' => $placa, 'vehiculo_tipo_id' => $vehiculoTipo->id, 'duracion_minutos' => $duracionMinutos],
            ]);
            return redirect()->route('reservas.revisar', $espacio, 303);
        }

        return response()
            ->view('reservas.confirmacion', compact(
                'placa',
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

    // SOLICITUD STORE

    public function store(Request $request, Espacio $espacio, TarifaService $tarifaService)
    {
        $placa = $this->validarPlaca($request);
        if (! app(ReservaDisponibilidadService::class)->estadoParaTarjeta($espacio)['puede_reservar']) {
            return redirect()
                ->route('public.disponibilidad')
                ->with('error', 'El espacio seleccionado ya no está disponible para reserva.');
        }

        $request->validate(['vehiculo_tipo_id' => 'required|exists:vehiculo_tipos,id',
            'duracion_minutos' => 'required|integer|in:60,120,180,240']);

        $vehiculoTipo = $espacio->vehiculoTipos()
            ->where('vehiculo_tipos.activo', true)
            ->where('vehiculo_tipos.id', $request->vehiculo_tipo_id)
            ->first();

        if (! $vehiculoTipo) {
            return redirect()
                ->route('reservas.create', $espacio)
                ->withErrors([
                    'vehiculo_tipo_id' => 'El tipo de vehículo seleccionado no está permitido para este espacio.',
                ])
                ->withInput();
        }

        $fechaHoraInicio = now('America/Lima')->startOfMinute();
        $fechaReserva = $fechaHoraInicio->toDateString();
        $horaInicio = $fechaHoraInicio->format('H:i');
        $duracionMinutos = (int) $request->duracion_minutos;

        $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes($duracionMinutos);

        try {
            $calculo = $tarifaService->calcularMonto($vehiculoTipo, $fechaHoraInicio, $duracionMinutos);
        } catch (RuntimeException $e) {
            return redirect()->route('reservas.create', $espacio)
                ->withErrors([
                    'tarifa' => $e->getMessage(),
                ])
                ->withInput();
        }

        $tarifa = $calculo['tarifa'];
        $montoTotal = $calculo['monto_total'];

        $reserva = DB::transaction(function () use (
            $placa,
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
            $espacio = Espacio::lockForUpdate()->findOrFail($espacio->id);
            abort_unless(app(ReservaDisponibilidadService::class)->estadoParaTarjeta($espacio)['puede_reservar'], 409, 'El espacio ya no está disponible.');
            $codigoReserva = 'RES-'.now('America/Lima')->format('YmdHis').'-'.Str::upper(Str::random(8));

            $reserva = Reserva::create([
                'placa' => $placa,
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

                'tolerancia_llegada_minutos' => 15,
                'minutos_fraccion_snapshot' => $tarifa->minutos_fraccion ?: 60,
                'estado' => 'pendiente_pago',
                'expires_at' => now('America/Lima')->addMinutes(ConfiguracionPago::actual()->minutos_pago),
                'reserva_inmediata' => true,
                'observacion' => 'Reserva inmediata desde la plataforma pública.',
            ]);

            Pago::create([
                'reserva_id' => $reserva->id,
                'user_id' => Auth::id(),
                'codigo_pago' => 'PAG-'.Str::uuid(),
                'metodo_pago' => 'manual',
                'monto' => $montoTotal,
                'estado' => 'pendiente',
            ]);

            return $reserva;
        });

        $request->session()->forget('reservation_review.'.$espacio->id);
        return redirect()
            ->route('pagos.show', $reserva)
            ->with('success', 'Reserva generada correctamente. Consulta el plazo de pago indicado en tu reserva.');
    }

    // SOLICITUD CANCELAR
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
            $reserva = Reserva::lockForUpdate()->findOrFail($reserva->id);
            abort_unless($reserva->estado === 'pendiente_pago', 409);
            abort_if($reserva->pagos()->where('estado', 'pendiente')->whereNotNull('enviado_at')->exists(), 409, 'Tu pago está en revisión. Comunícate con el negocio.');
            $reserva->update([
                'estado' => 'cancelada',
                'cancelado_at' => now('America/Lima'),
            ]);

            $reserva->pagos()
                ->where('estado', 'pendiente')
                ->update([
                    'estado' => 'cancelado',
                ]);

            if ($reserva->espacio && $reserva->espacio->getRawOriginal('estado_actual') === 'reservado') {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Reserva cancelada correctamente. El espacio fue liberado.');
    }

    // SOLICITUD REEMBOLSO
    public function solicitarReembolso(Reserva $reserva)
    {
        if ($reserva->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para solicitar reembolso de esta reserva.');
        }

        if ($reserva->estado !== 'confirmada' || $reserva->estadia()->exists()) {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Solo puedes solicitar reembolso antes de registrar el ingreso de una reserva confirmada.');
        }

        $pago = $reserva->pagos()
            ->where('estado', 'aprobado')
            ->latest()
            ->first();

        if (! $pago) {
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
            Espacio::lockForUpdate()->findOrFail($reserva->espacio_id);
            $reserva = Reserva::lockForUpdate()->findOrFail($reserva->id);
            abort_unless($reserva->estado === 'confirmada' && !$reserva->estadia()->exists(), 409, 'La reserva ya ingresó o cambió de estado.');
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

            if ($reserva->espacio && $reserva->espacio->getRawOriginal('estado_actual') === 'reservado') {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Solicitud de reembolso registrada correctamente. La administracion revisara tu caso.');
    }

}
