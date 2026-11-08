<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CobroPresencialService
{
    public function importe(int $base, array $data): array
    {
        $porcentaje = LiquidacionService::centavos($data['descuento_porcentaje'] ?? 0);
        $fijo = LiquidacionService::centavos($data['descuento_monto'] ?? 0);
        $descuento = (int) round($base * $porcentaje / 10000) + $fijo;
        if ($porcentaje < 0 || $porcentaje > 10000 || $fijo < 0 || $descuento > $base) {
            throw ValidationException::withMessages(['descuento_monto' => 'El descuento no puede superar el importe.']);
        }
        if ($descuento > 0 && blank($data['motivo_descuento'] ?? null)) {
            throw ValidationException::withMessages(['motivo_descuento' => 'Indica el motivo del descuento.']);
        }
        return ['porcentaje' => $porcentaje, 'fijo' => $fijo, 'descuento' => $descuento, 'total' => $base - $descuento];
    }

    public function registrar(array $data, int $total, User $operador, array $asociacion): array
    {
        if ($total === 0) return ['recibido' => null, 'vuelto' => null];
        $caja = app(CajaService::class)->abierta(true);
        if (empty($data['pago_verificado'])) {
            throw ValidationException::withMessages(['pago_verificado' => 'Confirma que recibiste y verificaste el pago.']);
        }
        $pagos = $data['pagos'] ?? [[
            'metodo_pago' => $data['metodo_pago'] ?? null,
            'monto' => $total / 100,
            'efectivo_recibido' => $data['efectivo_recibido'] ?? null,
            'referencia_pago' => $data['referencia_pago'] ?? null,
        ]];
        $aplicado = $recibido = $vuelto = 0;
        foreach ($pagos as $pago) {
            $metodo = $pago['metodo_pago'] ?? null;
            if (! in_array($metodo, ['efectivo', 'tarjeta', 'yape', 'plin', 'manual'], true)) {
                throw ValidationException::withMessages(['pagos' => 'Selecciona un medio de pago válido.']);
            }
            $monto = LiquidacionService::centavos($pago['monto'] ?? 0);
            if ($monto <= 0 || $aplicado + $monto > $total) {
                throw ValidationException::withMessages(['pagos' => 'Los pagos deben ser positivos y no superar el total.']);
            }
            $efectivo = $cambio = null;
            if ($metodo === 'efectivo') {
                $efectivo = LiquidacionService::centavos($pago['efectivo_recibido'] ?? 0);
                if ($efectivo < $monto) {
                    throw ValidationException::withMessages(['efectivo_recibido' => 'El efectivo recibido es menor al importe aplicado.']);
                }
                $cambio = $efectivo - $monto;
                $recibido += $efectivo;
                $vuelto += $cambio;
            }
            $referencia = trim((string) ($pago['referencia_pago'] ?? ''));
            $key = $referencia !== '' ? $metodo.':'.strtoupper($referencia) : null;
            if ($key && Pago::where('operacion_unica', $key)->exists()) {
                throw ValidationException::withMessages(['referencia_pago' => 'Esta operación ya se utilizó.']);
            }
            Pago::create($asociacion + ['caja_apertura_id' => $caja->id, 'codigo_pago' => 'CAJA-'.Str::uuid(), 'metodo_pago' => $metodo,
                'monto' => $monto / 100, 'efectivo_recibido' => $efectivo === null ? null : $efectivo / 100,
                'vuelto' => $cambio === null ? null : $cambio / 100, 'estado' => 'aprobado',
                'referencia_pago' => $referencia ?: null, 'operacion_unica' => $key,
                'pagado_at' => now(), 'revisado_at' => now(), 'revisado_por' => $operador->id,
                'motivo_revision' => 'Cobro presencial verificado en caja.']);
            $aplicado += $monto;
        }
        if ($aplicado !== $total) {
            throw ValidationException::withMessages(['pagos' => 'No se puede finalizar con saldo pendiente.']);
        }
        return ['recibido' => $recibido, 'vuelto' => $vuelto];
    }
}
