<?php

namespace App\Services;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservaService
{
    public function expirarReservasPendientes(): int
    {
        $reservas = Reserva::with(['espacio', 'pagos'])
            ->where('estado', 'pendiente_pago')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now('America/Lima'))
            ->get();

        $cantidadExpirada = 0;

        foreach ($reservas as $reserva) {
            DB::transaction(function () use ($reserva, &$cantidadExpirada) {
                $reserva->update([
                    'estado' => 'expirada',
                    'expirado_at' => now('America/Lima'),
                ]);

                $reserva->pagos()
                    ->where('estado', 'pendiente')
                    ->update([
                        'estado' => 'cancelado',
                    ]);

                if ($reserva->espacio && $reserva->espacio->estado_actual === 'reservado') {
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
        $reservas = Reserva::with('espacio')
            ->where('estado', 'confirmada')
            ->whereDate('fecha_reserva', '<=', now('America/Lima')->toDateString())
            ->get();

        $cantidadFinalizada = 0;

        foreach ($reservas as $reserva) {
            $fecha = $reserva->fecha_reserva->format('Y-m-d');
            $horaFin = $reserva->hora_fin;

            $fechaHoraFin = Carbon::parse($fecha . ' ' . $horaFin, 'America/Lima');

            if ($fechaHoraFin->lessThanOrEqualTo(now('America/Lima'))) {
                DB::transaction(function () use ($reserva, &$cantidadFinalizada) {
                    $reserva->update([
                        'estado' => 'finalizada',
                    ]);

                    if ($reserva->espacio && $reserva->espacio->estado_actual === 'reservado') {
                        $reserva->espacio->update([
                            'estado_actual' => 'libre',
                        ]);
                    }

                    $cantidadFinalizada++;
                });
            }
        }

        return $cantidadFinalizada;
    }

    public function procesarReservasAutomaticas(): array
    {
        return [
            'expiradas' => $this->expirarReservasPendientes(),
            'finalizadas' => $this->finalizarReservasConfirmadas(),
        ];
    }
}
