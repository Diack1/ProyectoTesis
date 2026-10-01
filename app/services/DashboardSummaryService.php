<?php

namespace App\Services;

use App\Models\{Estadia, Pago, Reembolso};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    public function summarize(Carbon $start, bool $monthly): array
    {
        $start = $monthly ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $end = $monthly ? $start->copy()->addMonth() : $start->copy()->addDay();
        $entries = $this->group(Estadia::query(), 'hora_ingreso', $start, $end, $monthly);
        $exits = $this->group(Estadia::whereNotNull('hora_salida'), 'hora_salida', $start, $end, $monthly);
        $reserved = $this->group(Estadia::whereNotNull('reserva_id'), 'hora_ingreso', $start, $end, $monthly);
        // Gross collections retain refunded payments; refunds are reported separately.
        $paid = $this->group(Pago::whereIn('estado', ['aprobado', 'reembolsado']), 'pagado_at', $start, $end, $monthly, 'SUM(monto)');
        $refunds = $this->group(Reembolso::where('estado', 'aprobado'), 'procesado_at', $start, $end, $monthly, 'SUM(monto)');
        $rows = [];
        $count = $monthly ? $start->daysInMonth : 24;
        for ($i = 0; $i < $count; $i++) {
            $key = str_pad((string)($monthly ? $i+1 : $i), 2, '0', STR_PAD_LEFT);
            $rows[] = ['label' => $monthly ? $key : $key.':00', 'entries' => (int)($entries[$key] ?? 0),
                'exits' => (int)($exits[$key] ?? 0), 'paid' => (float)($paid[$key] ?? 0), 'refunds' => (float)($refunds[$key] ?? 0)];
        }
        return ['rows' => $rows, 'entries' => $entries->sum(), 'exits' => $exits->sum(),
            'reserved' => $reserved->sum(), 'paid' => $paid->sum(), 'refunds' => $refunds->sum(),
            'net' => $paid->sum() - $refunds->sum(), 'start' => $start,
            'pending' => Pago::where('estado','pendiente')->whereNotNull('enviado_at')->sum('monto'),
            'maxActivity' => max(1, $entries->max() ?? 0, $exits->max() ?? 0),
            'maxMoney' => max(1, $paid->max() ?? 0, $refunds->max() ?? 0)];
    }

    private function group($query, string $column, Carbon $start, Carbon $end, bool $monthly, string $aggregate = 'COUNT(*)')
    {
        // Column and aggregate are constants from the callers, never user input.
        $format = $monthly ? '%d' : '%H';
        $expression = match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char($column, '".($monthly ? 'DD' : 'HH24')."')",
            'sqlite' => "strftime('$format', $column)",
            default => "DATE_FORMAT($column, '$format')",
        };
        return $query->where($column, '>=', $start)->where($column, '<', $end)
            ->selectRaw("$expression as bucket, $aggregate as total")
            ->groupByRaw($expression)->pluck('total', 'bucket');
    }
}
