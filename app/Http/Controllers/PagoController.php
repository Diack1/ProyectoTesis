<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    public function show(Reserva $reserva)
    {
        if ($reserva->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para ver este pago.');
        }

        $reserva->load(['espacio', 'pagos']);

        if ($reserva->estado === 'confirmada') {
            return redirect()
                ->route('reservas.index')
                ->with('success', 'Esta reserva ya fue pagada y confirmada correctamente.');
        }

        if ($reserva->estado !== 'pendiente_pago') {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Esta reserva ya no está disponible para pago.');
        }

        if ($reserva->expires_at && now('America/Lima')->greaterThan($reserva->expires_at)) {
            $this->expirarReserva($reserva);

            return redirect()
                ->route('reservas.index')
                ->with('error', 'El tiempo de pago expiró. La reserva fue liberada.');
        }

        $pago = $reserva->pagos()
            ->where('estado', 'pendiente')
            ->latest()
            ->first();

        return response()
            ->view('pagos.show', compact('reserva', 'pago'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function pagarSimulado(Reserva $reserva)
    {
        if ($reserva->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para pagar esta reserva.');
        }

        $reserva->load(['espacio', 'pagos']);

        if ($reserva->estado === 'confirmada') {
            return redirect()
                ->route('reservas.index')
                ->with('success', 'Esta reserva ya estaba pagada y confirmada.');
        }

        if ($reserva->estado !== 'pendiente_pago') {
            return redirect()
                ->route('reservas.index')
                ->with('error', 'Esta reserva ya no está disponible para pago.');
        }

        if ($reserva->expires_at && now('America/Lima')->greaterThan($reserva->expires_at)) {
            $this->expirarReserva($reserva);

            return redirect()
                ->route('reservas.index')
                ->with('error', 'El tiempo de pago expiró. La reserva fue liberada.');
        }

        DB::transaction(function () use ($reserva) {
            $reserva = Reserva::where('id', $reserva->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->estado !== 'pendiente_pago') {
                return;
            }

            $pago = $reserva->pagos()
                ->where('estado', 'pendiente')
                ->latest()
                ->first();

            if ($pago) {
                $pago->update([
                    'estado' => 'aprobado',
                    'referencia_pago' => 'SIM-' . now('America/Lima')->format('YmdHis') . '-' . Auth::id(),
                    'pagado_at' => now('America/Lima'),
                ]);
            }

            $reserva->update([
                'estado' => 'confirmada',
                'pagado_at' => now('America/Lima'),
            ]);

            if ($reserva->espacio) {
                $reserva->espacio->update([
                    'estado_actual' => 'reservado',
                ]);
            }
        });

        return redirect()
            ->route('reservas.index')
            ->with('success', 'Pago simulado aprobado. Tu reserva fue confirmada correctamente.');
    }

    private function expirarReserva(Reserva $reserva): void
    {
        DB::transaction(function () use ($reserva) {
            $reserva = Reserva::with('espacio')
                ->whereKey($reserva->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->estado !== 'pendiente_pago') {
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

            if ($reserva->espacio) {
                $reserva->espacio->update([
                    'estado_actual' => 'libre',
                ]);
            }
        });
    }
}
