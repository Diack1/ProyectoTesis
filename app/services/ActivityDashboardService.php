<?php

namespace App\Services;

use App\Models\{CajaApertura, CajaMovimiento, Estadia, Pago, Reembolso, User, Venta};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityDashboardService
{
    public function summarize(Carbon $from, Carbon $to, string $shift, ?User $viewer = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $start = $shift === 'noche' ? $from->copy()->setTime(19, 0) : $from;
        $end = match ($shift) {
            'manana' => $to->copy()->setTime(13, 0),
            'tarde' => $to->copy()->setTime(19, 0),
            'noche' => $to->copy()->addDay()->setTime(6, 0),
            default => $to->copy()->addDay(),
        };
        $hours = match ($shift) {
            'manana' => range(6, 12),
            'tarde' => range(13, 18),
            'noche' => array_merge(range(19, 23), range(0, 5)),
            default => range(0, 23),
        };
        $sales = array_fill_keys($hours, 0);
        $income = array_fill_keys($hours, 0.0);
        $exits = array_fill_keys($hours, 0);
        $methods = array_fill_keys(['efectivo', 'tarjeta', 'yape', 'plin', 'manual'], 0.0);

        $boxId = null;
        if ($viewer && $viewer->role !== 'super_admin') {
            $boxId = CajaApertura::where('abierta_unica', 1)->where('operador_id', $viewer->id)->value('id');
        }
        $salesQuery = $this->window(Venta::where('estado', '!=', 'anulada'), 'fecha', $start, $end, $shift);
        if ($viewer && $viewer->role !== 'super_admin') {
            $salesQuery->where(fn ($q) => $q->where('caja_apertura_id', $boxId ?? 0)
                ->orWhere(fn ($q) => $q->whereNull('caja_apertura_id')
                    ->whereHas('pagos', fn ($p) => $p->where('caja_apertura_id', $boxId ?? 0))));
        }
        foreach ($this->hourly(clone $salesQuery, 'fecha', 'COUNT(*)') as $hour => $amount) $sales[(int) $hour] = (int) $amount;
        $discounts = (float) (clone $salesQuery)->sum('descuento');
        $salesTotal = (float) (clone $salesQuery)->sum('total');

        $exitQuery = $this->window(Estadia::whereNotNull('hora_salida'), 'hora_salida', $start, $end, $shift);
        foreach ($this->hourly($exitQuery, 'hora_salida', 'COUNT(*)') as $hour => $amount) $exits[(int) $hour] = (int) $amount;

        $payments = $this->window(Pago::whereNotNull('pagado_at')
            ->where(fn ($q) => $q->whereIn('estado', ['aprobado', 'reembolsado'])->orWhere('reportable_historico', true)),
            'pagado_at', $start, $end, $shift);
        if ($viewer && $viewer->role !== 'super_admin') $payments->where('caja_apertura_id', $boxId ?? 0);
        foreach ($this->hourlyMethods($payments, 'pagado_at') as $row) {
            $this->addMethod($methods, $income, $row->metodo_pago, (int) $row->hour, (float) $row->total);
        }

        $adjustments = $this->window(CajaMovimiento::where('ajuste_venta', true), 'created_at', $start, $end, $shift);
        if ($viewer && $viewer->role !== 'super_admin') $adjustments->where('caja_apertura_id', $boxId ?? 0);
        foreach ($this->hourlyMethods($adjustments, 'created_at', true) as $row) {
            $this->addMethod($methods, $income, $row->metodo_pago, (int) $row->hour,
                ($row->tipo === 'ingreso' ? 1 : -1) * (float) $row->total);
        }

        $refunds = $this->window(Reembolso::with('pago:id,metodo_pago,caja_apertura_id')->where('estado', 'aprobado'),
            'procesado_at', $start, $end, $shift);
        if ($viewer && $viewer->role !== 'super_admin') {
            $refunds->whereHas('pago', fn ($p) => $p->where('caja_apertura_id', $boxId ?? 0));
        }
        $refundTotal = 0.0;
        foreach ($refunds->get(['id', 'pago_id', 'monto', 'procesado_at']) as $refund) {
            $amount = (float) $refund->monto;
            $refundTotal += $amount;
            $this->addMethod($methods, $income, $refund->pago?->metodo_pago ?? 'manual',
                $refund->procesado_at->hour, -$amount);
        }

        $manual = $this->window(CajaMovimiento::where('ajuste_venta', false), 'created_at', $start, $end, $shift);
        if ($viewer && $viewer->role !== 'super_admin') $manual->where('caja_apertura_id', $boxId ?? 0);
        $extraIncome = (float) (clone $manual)->where('tipo', 'ingreso')->sum('monto');
        $expenses = (float) (clone $manual)->where('tipo', 'egreso')->sum('monto') + $refundTotal;

        $rows = [];
        foreach ($hours as $hour) $rows[] = ['label' => sprintf('%02d:00', $hour), 'ventas' => $sales[$hour],
            'ingreso' => round($income[$hour], 2), 'salidas' => $exits[$hour]];
        return ['rows' => $rows, 'methods' => array_map(fn ($value) => round($value, 2), $methods),
            'sales_count' => array_sum($sales), 'sales_total' => round($salesTotal, 2),
            'discounts' => round($discounts, 2), 'expenses' => round($expenses, 2),
            'extra_income' => round($extraIncome, 2), 'exits' => array_sum($exits),
            'start' => $from, 'end' => $to, 'shift' => $shift];
    }

    private function window($query, string $column, Carbon $start, Carbon $end, string $shift)
    {
        $query->where($column, '>=', $start)->where($column, '<', $end);
        return match ($shift) {
            'manana' => $query->whereTime($column, '>=', '06:00:00')->whereTime($column, '<', '13:00:00'),
            'tarde' => $query->whereTime($column, '>=', '13:00:00')->whereTime($column, '<', '19:00:00'),
            'noche' => $query->where(fn ($q) => $q->whereTime($column, '>=', '19:00:00')->orWhereTime($column, '<', '06:00:00')),
            default => $query,
        };
    }

    private function hourExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char($column, 'HH24')",
            'sqlite' => "strftime('%H', $column)",
            default => "DATE_FORMAT($column, '%H')",
        };
    }

    private function hourly($query, string $column, string $aggregate)
    {
        $hour = $this->hourExpression($column);
        return $query->selectRaw("$hour as hour, $aggregate as total")
            ->groupByRaw($hour)->pluck('total', 'hour');
    }

    private function hourlyMethods($query, string $column, bool $withType = false)
    {
        $hour = $this->hourExpression($column);
        $group = $withType ? "$hour, metodo_pago, tipo" : "$hour, metodo_pago";
        return $query->selectRaw("$hour as hour, metodo_pago, ".($withType ? 'tipo, ' : '')."SUM(monto) as total")
            ->groupByRaw($group)->get();
    }

    private function addMethod(array &$methods, array &$income, string $method, int $hour, float $amount): void
    {
        $method = array_key_exists($method, $methods) ? $method : 'manual';
        $methods[$method] += $amount;
        $income[$hour] += $amount;
    }
}
