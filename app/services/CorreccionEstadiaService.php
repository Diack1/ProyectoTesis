<?php

namespace App\Services;

use App\Models\Estadia;
use App\Models\EstadiaAuditoria;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorreccionEstadiaService
{
    public function corregir(Estadia $ticket, array $data, User $usuario): Estadia
    {
        try {
            return DB::transaction(function () use ($ticket, $data, $usuario) {
                $estadia = Estadia::lockForUpdate()->findOrFail($ticket->id);
                if ($estadia->hora_salida) {
                    throw ValidationException::withMessages(['registro' => 'El ticket ya está finalizado y no admite correcciones.']);
                }
                $tipoId = (int) ($data['vehiculo_tipo_id'] ?? $estadia->vehiculo_tipo_id);
                if (! $estadia->espacio->vehiculoTipos()->where('vehiculo_tipos.id', $tipoId)->where('vehiculo_tipos.activo', true)->exists()) {
                    throw ValidationException::withMessages(['vehiculo_tipo_id' => 'El tipo de vehículo no está permitido en este espacio.']);
                }
                if (($estadia->reserva_id || $estadia->abono_id) && $tipoId !== $estadia->vehiculo_tipo_id) {
                    throw ValidationException::withMessages(['vehiculo_tipo_id' => 'El tipo de vehículo de una reserva o abono vinculado no puede cambiarse.']);
                }
                $tarifa = isset($data['tarifa_id']) ? Tarifa::where('activo', true)->where('vehiculo_tipo_id', $tipoId)
                    ->whereIn('tipo_tarifa', ['por_hora', 'bloque_horas'])->find($data['tarifa_id']) : null;
                if (isset($data['tarifa_id']) && ! $tarifa) {
                    throw ValidationException::withMessages(['tarifa_id' => 'Selecciona una tarifa activa del tipo de vehículo elegido.']);
                }
                if ($tipoId !== $estadia->vehiculo_tipo_id && ! $tarifa) {
                    throw ValidationException::withMessages(['tarifa_id' => 'Selecciona una tarifa para el nuevo tipo de vehículo.']);
                }
                if (($estadia->reserva_id || $estadia->abono_id) && $tarifa) {
                    throw ValidationException::withMessages(['tarifa_id' => 'La tarifa de una reserva o abono vinculado no puede cambiarse.']);
                }
                $snapshot = $tarifa ? app(TarifaService::class)->snapshotIngreso($tarifa) : $estadia->tarifa_snapshot;
                $antes = ['placa' => $estadia->placa, 'tipo_vehiculo' => $estadia->vehiculoTipo->nombre,
                    'tarifa' => $estadia->tarifa_snapshot['nombre'] ?? 'Tarifa registrada', 'tarifa_snapshot' => $estadia->tarifa_snapshot];
                $nuevos = ['placa' => $data['placa'], 'tipo_vehiculo' => $estadia->espacio->vehiculoTipos()->findOrFail($tipoId)->nombre,
                    'tarifa' => $snapshot['nombre'] ?? 'Tarifa registrada', 'tarifa_snapshot' => $snapshot];
                if ($antes === $nuevos) {
                    throw ValidationException::withMessages(['registro' => 'No hay cambios para guardar.']);
                }
                if ($estadia->reserva_id && $nuevos['placa'] !== $antes['placa']) {
                    throw ValidationException::withMessages(['placa' => 'La placa de una reserva vinculada se corrige desde el registro de la reserva.']);
                }
                if (Estadia::activas()->where('placa', $nuevos['placa'])->where('id', '!=', $estadia->id)->exists()) {
                    throw ValidationException::withMessages(['placa' => 'Esta placa ya tiene otro ticket activo.']);
                }
                $estadia->update(['placa' => $nuevos['placa'], 'placa_activa' => $nuevos['placa'],
                    'vehiculo_tipo_id' => $tipoId, 'tarifa_id' => $tarifa?->id ?? $estadia->tarifa_id, 'tarifa_snapshot' => $snapshot]);
                EstadiaAuditoria::create(['estadia_id' => $estadia->id, 'usuario_id' => $usuario->id,
                    'accion' => 'correccion', 'valores_anteriores' => $antes, 'valores_nuevos' => $nuevos,
                    'motivo' => $data['motivo']]);
                return $estadia;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['placa' => 'Esta placa ya tiene otro ticket activo.']);
        }
    }
}
