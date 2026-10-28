<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CobroLlegadaService
{
    // Called only inside the entry transaction, with the reservation locked.
    public function registrar(Reserva $reserva, array $data, User $operador): void
    {
        if (empty($data['pago_verificado'])) {
            throw ValidationException::withMessages(['pago_verificado' => 'Verifica que recibiste el pago antes de confirmar la entrada.']);
        }
        $metodo = $data['metodo_pago'] ?? null;
        if (! in_array($metodo, ['efectivo', 'yape', 'plin'], true)) {
            throw ValidationException::withMessages(['metodo_pago' => 'Selecciona el medio de pago recibido.']);
        }
        $key = null;
        if ($metodo === 'efectivo') {
            if (LiquidacionService::centavos($data['efectivo_recibido'] ?? 0) < LiquidacionService::centavos($reserva->monto_total)) {
                throw ValidationException::withMessages(['efectivo_recibido' => 'El efectivo recibido es insuficiente.']);
            }
        } else {
            $referencia = strtoupper(trim($data['referencia_pago'] ?? ''));
            if (! preg_match('/\A[A-Z0-9-]{1,80}\z/', $referencia)) {
                throw ValidationException::withMessages(['referencia_pago' => 'Registra la operación que verificaste en la cuenta del negocio.']);
            }
            $key = $metodo.':'.$referencia;
            if (Pago::where('operacion_unica', $key)->exists()) {
                throw ValidationException::withMessages(['referencia_pago' => 'Esta operación ya se utilizó.']);
            }
        }
        if ($reserva->pagos()->whereNotNull('enviado_at')->where('estado', 'pendiente')->exists()) {
            throw ValidationException::withMessages(['reserva_id' => 'Esta reserva tiene un pago en revisión. Resuélvelo antes de cobrar.']);
        }
        $pago = $reserva->pagos()->where('estado', 'pendiente')->whereNull('enviado_at')->first();
        $values = ['metodo_pago' => $metodo, 'monto' => $reserva->monto_total, 'estado' => 'aprobado',
            'referencia_pago' => $metodo === 'efectivo' ? null : $referencia, 'operacion_unica' => $key,
            'pagado_at' => now(), 'revisado_at' => now(), 'revisado_por' => $operador->id,
            'motivo_revision' => 'Cobro al llegar verificado por el encargado.'];
        if ($pago) {
            $pago->update($values);
        } else {
            $reserva->pagos()->create($values + ['user_id' => $reserva->user_id, 'codigo_pago' => 'CAJA-'.Str::uuid()]);
        }
        $reserva->update(['estado' => 'confirmada', 'pagado_at' => now(), 'expires_at' => null]);
    }
}
