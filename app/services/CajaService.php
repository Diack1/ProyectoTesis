<?php

namespace App\Services;

use App\Models\{CajaApertura, CajaMovimiento, Pago, Reembolso};
use Illuminate\Validation\ValidationException;

class CajaService
{
    public function abierta(bool $bloquear = false): CajaApertura
    {
        $query = CajaApertura::where('abierta_unica', 1);
        $caja = ($bloquear ? $query->lockForUpdate() : $query)->first();
        if (!$caja) {
            throw ValidationException::withMessages(['caja' => 'Abre la caja antes de confirmar un cobro.']);
        }
        if (auth()->check() && auth()->user()->role !== 'super_admin' && $caja->operador_id !== auth()->id()) {
            throw ValidationException::withMessages(['caja' => 'La caja abierta pertenece a otro cajero.']);
        }
        return $caja;
    }

    public function arqueo(CajaApertura $caja): array
    {
        $pagos = Pago::where('caja_apertura_id', $caja->id)
            ->whereIn('estado', ['aprobado', 'reembolsado'])->whereNotNull('pagado_at')
            ->orderBy('pagado_at')->orderBy('id')->get();
        $reembolsos = Reembolso::with('pago')->where('estado', 'aprobado')
            ->whereHas('pago', fn ($query) => $query->where('caja_apertura_id', $caja->id))
            ->orderBy('procesado_at')->get();
        $ventas = $pagos->groupBy('metodo_pago')->map(fn ($grupo) => $grupo->sum(fn ($pago) => LiquidacionService::centavos($pago->monto)))->all();
        foreach ($reembolsos as $reembolso) {
            $medio = $reembolso->pago?->metodo_pago ?? 'manual';
            $ventas[$medio] = ($ventas[$medio] ?? 0) - LiquidacionService::centavos($reembolso->monto);
        }
        $movimientos = CajaMovimiento::with('operador')->where('caja_apertura_id', $caja->id)->orderByDesc('id')->get();
        foreach ($movimientos->where('ajuste_venta', true) as $movimiento) {
            $medio = $movimiento->metodo_pago;
            $ventas[$medio] = ($ventas[$medio] ?? 0) + ($movimiento->tipo === 'ingreso' ? 1 : -1) * LiquidacionService::centavos($movimiento->monto);
        }
        $manuales = $movimientos->where('ajuste_venta', false);
        $ingresos = $manuales->where('tipo', 'ingreso')->sum(fn ($m) => LiquidacionService::centavos($m->monto));
        $egresos = $manuales->where('tipo', 'egreso')->sum(fn ($m) => LiquidacionService::centavos($m->monto));
        $efectivoManual = $manuales->where('metodo_pago', 'efectivo')->sum(fn ($m) => ($m->tipo === 'ingreso' ? 1 : -1) * LiquidacionService::centavos($m->monto));
        $inicial = LiquidacionService::centavos($caja->monto_inicial);
        return compact('pagos', 'reembolsos', 'movimientos', 'ventas', 'ingresos', 'egresos') + [
            'total_ventas' => array_sum($ventas),
            'efectivo_esperado' => $inicial + ($ventas['efectivo'] ?? 0) + $efectivoManual,
            'total_caja' => $inicial + array_sum($ventas) + $ingresos - $egresos,
        ];
    }
}
