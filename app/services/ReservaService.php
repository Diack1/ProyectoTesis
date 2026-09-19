<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\Reembolso;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

class ReservaService
{
    public function expirarReservasPendientes(): int
    {
        $reservas = Reserva::with(['espacio', 'pagos'])
            ->where('estado', 'pendiente_pago')
            ->whereDoesntHave('pagos', fn ($q) => $q->where('estado', 'pendiente')->whereNotNull('enviado_at'))
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now('America/Lima'))
            ->get();

        $cantidadExpirada = 0;

        foreach ($reservas as $reserva) {
            DB::transaction(function () use ($reserva, &$cantidadExpirada) {
                $reserva = Reserva::lockForUpdate()->findOrFail($reserva->id);
                if ($reserva->estado !== 'pendiente_pago' || ! $reserva->expires_at || $reserva->expires_at->isFuture()) {
                    return;
                }
                if ($reserva->pagos()->where('estado', 'pendiente')->whereNotNull('enviado_at')->exists()) {
                    return;
                }
                $reserva->update([
                    'estado' => 'expirada',
                    'expirado_at' => now('America/Lima'),
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

                $cantidadExpirada++;
            });
        }

        return $cantidadExpirada;
    }

    public function finalizarReservasConfirmadas(): int
    {
        // Compatibility entry point: confirmed reservations only end through checkout.
        return $this->procesarInasistencias();
    }

    public function procesarInasistencias(): int
    {
        $cantidad = 0;
        Reserva::where('estado', 'confirmada')->whereNull('inasistencia_at')->whereDoesntHave('estadia')
            ->whereDate('fecha_reserva', '<=', now()->toDateString())->eachById(function ($reserva) use (&$cantidad) {
                if (now()->lte($reserva->limite_llegada)) {
                    return;
                }
                DB::transaction(function () use ($reserva, &$cantidad) {
                    $espacio = Espacio::lockForUpdate()->findOrFail($reserva->espacio_id);
                    $reserva = Reserva::lockForUpdate()->findOrFail($reserva->id);
                    if ($reserva->estado !== 'confirmada' || $reserva->inasistencia_at || $reserva->estadia()->exists() || now()->lte($reserva->limite_llegada)) {
                        return;
                    }
                    $pago = $reserva->pagos()->where('estado', 'aprobado')->whereNull('estadia_id')->first();
                    $reserva->update(['inasistencia_at' => now(), 'estado' => $pago ? 'reembolso_solicitado' : 'cancelada']);
                    if ($pago) {
                        Reembolso::firstOrCreate(['reserva_id' => $reserva->id, 'pago_id' => $pago->id], [
                            'user_id' => $reserva->user_id, 'monto' => $pago->monto, 'estado' => 'solicitado', 'solicitado_at' => now(),
                            'motivo' => 'Inasistencia: venció la tolerancia de llegada. Revisar el pago y decidir el reembolso manual.',
                        ]);
                    }
                    if ($espacio->getRawOriginal('estado_actual') === 'reservado') {
                        $espacio->update(['estado_actual' => 'libre']);
                    }
                    $cantidad++;
                });
            });

        return $cantidad;
    }

    public function procesarReservasAutomaticas(): array
    {
        return ['expiradas' => $this->expirarReservasPendientes(), 'inasistencias' => $this->procesarInasistencias()];
    }
}
