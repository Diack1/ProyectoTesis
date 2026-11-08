<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\Pago;
use App\Models\RegistroOcupacion;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EstadiaService
{
    public function ingresar(array $data, User $operador): Estadia
    {
        try {
            return DB::transaction(function () use ($data, $operador) {
                // Every operation locks the space before the reservation and stay.
                $espacio = Espacio::lockForUpdate()->findOrFail($data['espacio_id']);
                $reserva = empty($data['reserva_id']) ? null : Reserva::lockForUpdate()->findOrFail($data['reserva_id']);
                if (! $espacio->activo || in_array($espacio->getRawOriginal('estado_actual'), ['ocupado', 'mantenimiento'])) {
                    throw ValidationException::withMessages(['espacio_id' => 'El espacio no está libre. Verifica su estado antes de registrar el ingreso.']);
                }
                if (Estadia::activas()->where('espacio_id', $espacio->id)->exists()) {
                    throw ValidationException::withMessages(['espacio_id' => 'Este espacio ya tiene un vehículo registrado.']);
                }
                if (Estadia::activas()->where('placa', $data['placa'])->exists()) {
                    throw ValidationException::withMessages(['placa' => 'Esta placa ya tiene un ingreso sin salida.']);
                }
                if ($espacio->modo_monitoreo === 'sensor' && $espacio->sensor?->integracion_iot && ! $espacio->sensor->lectura_vigente) {
                    throw ValidationException::withMessages(['espacio_id' => 'No hay una lectura confiable reciente. Revisa el sensor o usa control manual.']);
                }
                $asignacion = $espacio->abonoVigente()->with('abonado')->first();
                $abono = null;
                if (!empty($data['abono_id'])) {
                    if (!$asignacion || $asignacion->id != $data['abono_id'] || !in_array($data['placa'], $asignacion->abonado->placas, true) || $reserva) {
                        throw ValidationException::withMessages(['abono_id'=>'El abono debe estar pagado, vigente y corresponder al espacio y placa.']);
                    }
                    $abono = $asignacion;
                }
                $casillero = app(CasilleroService::class)->validarIngreso($data);
                if ($reserva) {
                    $cobrarLlegada = $reserva->pago_al_llegar && $reserva->estado === 'pendiente_pago';
                    if (($cobrarLlegada && (! $reserva->expires_at || now()->gt($reserva->expires_at) || $reserva->pagoAprobado()->exists()))
                      || (! $cobrarLlegada && ($reserva->estado !== 'confirmada' || ! $reserva->pagoAprobado()->exists()))
                      || $reserva->inasistencia_at || ($reserva->pago_al_llegar && $reserva->placa !== $data['placa'])
                      || $reserva->espacio_id !== $espacio->id || $reserva->vehiculo_tipo_id != (int) $data['vehiculo_tipo_id'] || $reserva->estadia()->exists()) {
                        throw ValidationException::withMessages(['reserva_id' => 'La reserva debe estar vigente, corresponder a la placa, espacio y tipo de vehículo, y admitir el cobro o tener un pago aprobado.']);
                    }
                    if (now()->gt($reserva->limite_llegada)) {
                        throw ValidationException::withMessages(['reserva_id' => 'La tolerancia de llegada terminó. La administración debe resolver la reserva.']);
                    }
                }
                if ($espacio->reservas()->whereIn('estado', ['pendiente_pago', 'confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia')
                    ->when($reserva, fn ($q) => $q->where('id', '!=', $reserva->id))->exists()) {
                    throw ValidationException::withMessages(['espacio_id' => 'Este espacio está comprometido con otra reserva.']);
                }
                $tipo = $espacio->vehiculoTipos()->where('vehiculo_tipos.id', $data['vehiculo_tipo_id'])->where('vehiculo_tipos.activo', true)->first();
                if (! $tipo) {
                    throw ValidationException::withMessages(['vehiculo_tipo_id' => 'Este tipo de vehículo no está permitido en el espacio.']);
                }
                $sensor = $espacio->modo_monitoreo === 'sensor';
                if ($sensor && (! $espacio->sensor || $espacio->sensor->estado !== 'activo')) {
                    throw ValidationException::withMessages(['espacio_id' => 'No hay un sensor activo configurado. Cambia el espacio a control manual para operar sin sensor.']);
                }
                $tarifaSeleccionada = null;
                if ($reserva) {
                    $snapshot = ['nombre' => $reserva->tarifa_nombre, 'tipo_tarifa' => $reserva->tipo_tarifa,
                        'monto_por_hora' => $reserva->tarifa_hora, 'tolerancia_minutos' => $reserva->tolerancia_minutos,
                        'penalidad_por_fraccion' => $reserva->penalidad_por_fraccion, 'minutos_fraccion' => $reserva->minutos_fraccion_snapshot];
                    $tarifaSeleccionada = $reserva->tarifa_id;
                } else {
                    $tarifa = !empty($data['tarifa_id']) ? \App\Models\Tarifa::where('vehiculo_tipo_id', $tipo->id)
                        ->where('activo', true)->whereIn('tipo_tarifa', ['por_hora', 'bloque_horas'])->find($data['tarifa_id']) : null;
                    if (!$tarifa && !$abono) {
                        throw ValidationException::withMessages(['tarifa_id' => 'Selecciona una tarifa activa compatible con el vehículo.']);
                    }
                    if (!$tarifa) {
                        $tarifa = \App\Models\Tarifa::where('vehiculo_tipo_id', $tipo->id)->where('activo', true)
                            ->where('tipo_tarifa', 'por_hora')->orderByDesc('prioridad')->first();
                        if (!$tarifa) throw ValidationException::withMessages(['tarifa_id' => 'Configura una tarifa por hora para los excesos del abono.']);
                    }
                    $snapshot = app(TarifaService::class)->snapshotIngreso($tarifa);
                    $tarifaSeleccionada = $tarifa->id;
                }
                if ($reserva && $cobrarLlegada) {
                    app(CobroLlegadaService::class)->registrar($reserva, $data, $operador);
                }
                $estadia = Estadia::create([
                    'abono_id'=>$abono?->id, 'autoriza_movimiento'=>!empty($data['autoriza_movimiento']),
                    'llave_recibida'=>!empty($data['llave_recibida']), 'ubicacion_llave'=>$casillero,
                    'casillero_activo'=>$casillero, 'salida_prevista'=>$data['salida_prevista'] ?? null,
                    'codigo_ticket' => 'PK-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'placa' => $data['placa'], 'placa_activa' => $data['placa'], 'espacio_id' => $espacio->id, 'espacio_activo_id' => $espacio->id,
                    'vehiculo_tipo_id' => $tipo->id, 'tarifa_id' => $tarifaSeleccionada, 'reserva_id' => $reserva?->id, 'operador_ingreso_id' => $operador->id,
                    'hora_ingreso' => now(), 'inicio_cobro' => $sensor ? null : now(), 'fuente_inicio' => $sensor ? 'sensor' : 'manual',
                    'tarifa_snapshot' => $snapshot, 'minutos_contratados' => $reserva?->duracion_minutos ?? 0, 'monto_adelantado' => $reserva?->monto_total ?? 0,
                ]);
                if (! $sensor) {
                    $espacio->update(['estado_actual' => 'ocupado']);
                    RegistroOcupacion::create(['espacio_id' => $espacio->id, 'estado_detectado' => 'ocupado', 'fecha_hora' => now(), 'origen' => 'ingreso_ticket']);
                }

                return $estadia;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'casillero_activo')) {
                throw ValidationException::withMessages(['ubicacion_llave' => 'Ese casillero acaba de ocuparse. Actualiza el formulario y elige otro.']);
            }
            throw ValidationException::withMessages(['placa' => 'La placa, el espacio o la reserva ya tienen un ingreso activo. Actualiza la página.']);
        }
    }

    public function confirmarInicioManual(Estadia $estadia, Carbon $inicio, string $motivo, User $operador): void
    {
        DB::transaction(function () use ($estadia, $inicio, $motivo, $operador) {
            $espacio = Espacio::lockForUpdate()->findOrFail($estadia->espacio_id);
            $estadia = Estadia::lockForUpdate()->findOrFail($estadia->id);
            if ($estadia->hora_salida || $estadia->inicio_cobro || $inicio->lt($estadia->hora_ingreso->copy()->startOfMinute()) || $inicio->gt(now())) {
                throw ValidationException::withMessages(['inicio_cobro' => 'El inicio ya existe o la hora no pertenece a esta estadía.']);
            }
            if ($inicio->lt($estadia->hora_ingreso)) {
                $inicio = $estadia->hora_ingreso;
            }
            $estadia->update(['inicio_cobro' => $inicio, 'fuente_inicio' => 'manual_contingencia', 'motivo_inicio_manual' => $motivo, 'inicio_confirmado_por' => $operador->id]);
            $espacio->update(['estado_actual' => 'ocupado']);
        });
    }

    public function salir(Estadia $estadia, array $data, User $operador): Estadia
    {
        try {
            return DB::transaction(function () use ($estadia, $data, $operador) {
                $espacio = Espacio::lockForUpdate()->findOrFail($estadia->espacio_id);
                $reserva = $estadia->reserva_id ? Reserva::lockForUpdate()->findOrFail($estadia->reserva_id) : null;
                $estadia = Estadia::lockForUpdate()->findOrFail($estadia->id);
                if ($estadia->espacio_id !== $espacio->id) { throw ValidationException::withMessages(['salida'=>'El vehículo fue reubicado. Actualiza el ticket.']); }
                if ($estadia->hora_salida) {
                    throw ValidationException::withMessages(['salida' => 'Este ticket ya tiene salida registrada.']);
                }
                try {
                    $quote = json_decode(Crypt::decryptString($data['cotizacion']), true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages(['salida' => 'La cotización no es válida. Vuelve a consultar el ticket.']);
                }
                if (($quote['id'] ?? null) !== $estadia->id || ($quote['inicio'] ?? null) !== $estadia->inicio_cobro?->toIso8601String()) {
                    throw ValidationException::withMessages(['salida' => 'El inicio cambió. Vuelve a calcular el importe.']);
                }
                $corte = Carbon::parse($quote['corte']);
                if ($corte->gt(now()) || $corte->lt(now()->subMinutes(5))) {
                    throw ValidationException::withMessages(['salida' => 'La cotización venció. Actualiza el cálculo antes de cobrar.']);
                }
                $liquidacion = app(LiquidacionService::class)->calcular($estadia, $corte);
                $base = $liquidacion['saldo_centavos'];
                $cobro = app(CobroPresencialService::class);
                $importe = $cobro->importe($base, $data);
                $porcentaje = $importe['porcentaje'];
                $fijo = $importe['fijo'];
                $descuento = $importe['descuento'];
                $saldo = $importe['total'];
                $resultado = $cobro->registrar($data, $saldo, $operador,
                    ['estadia_id' => $estadia->id, 'user_id' => $reserva?->user_id]);
                $recibido = $resultado['recibido'];
                $vuelto = $resultado['vuelto'];
                $estadia->update(['hora_salida' => now(), 'liquidado_hasta' => $corte, 'operador_salida_id' => $operador->id,
                    'placa_activa' => null, 'espacio_activo_id' => null, 'casillero_activo' => null, 'minutos_cobrados' => $liquidacion['minutos'],
                    'monto_total' => ($liquidacion['total_centavos'] - $descuento) / 100, 'monto_exceso' => $liquidacion['exceso_centavos'] / 100,
                    'descuento_porcentaje' => $porcentaje / 100, 'descuento_monto' => $fijo / 100,
                    'motivo_descuento' => $data['motivo_descuento'] ?? null, 'cliente_cobro_id' => $data['cliente_cobro_id'] ?? null,
                    'tipo_comprobante' => $data['tipo_comprobante'] ?? 'boleta', 'tipo_impresion' => $data['tipo_impresion'] ?? 'detallado',
                    'saldo_cobrado' => $saldo / 100, 'efectivo_recibido' => $recibido === null ? null : $recibido / 100, 'vuelto' => $vuelto === null ? null : $vuelto / 100]);
                if ($reserva) {
                    $reserva->update(['estado' => 'finalizada', 'monto_penalidad' => $liquidacion['exceso_centavos'] / 100]);
                }
                if ($espacio->modo_monitoreo === 'manual') {
                    $espacio->update(['estado_actual' => 'libre']);
                    RegistroOcupacion::create(['espacio_id' => $espacio->id, 'estado_detectado' => 'libre', 'fecha_hora' => now(), 'origen' => 'salida_ticket']);
                }

                app(VentaService::class)->desdeOrigen('estadia', $estadia->id);

                return $estadia;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['referencia_pago' => 'La operación o el ticket ya tienen un cobro registrado.']);
        }
    }
}
