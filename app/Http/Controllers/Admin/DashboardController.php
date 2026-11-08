<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CajaMovimiento, Espacio, Estadia, Pago, Reserva};
use App\Services\{MapaOperativoService, ReservaDisponibilidadService, ReservaService};

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request, ReservaService $reservas, ReservaDisponibilidadService $disponibilidad, MapaOperativoService $mapa)
    {
        $reservas->procesarReservasAutomaticas();
        $espacios = Espacio::conEstadoOperativo()->with(['sensor','vehiculoTipos.tarifas',
            'estadias' => fn ($q) => $q->with('reserva','abono')->activas(),
            'reservas' => fn ($q) => $q->with(['usuario', 'pagos'])->whereIn('estado',['pendiente_pago','confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia'),
        ])->where('activo',true)->orderBy('codigo')->get();
        $disponibilidadPorEspacio = $mapa->estados($espacios, $disponibilidad);
        $libres = $espacios->where('estado_actual','libre')->count();
        $activas = Estadia::activas()->count();
        $pendientes = Pago::where('estado','pendiente')->whereNotNull('enviado_at')->count();
        $cobros = Pago::where(fn ($q) => $q->where('estado','aprobado')->orWhere('reportable_historico',true))
            ->whereDate('pagado_at',today())->sum('monto')
            + CajaMovimiento::where('ajuste_venta',true)->where('tipo','ingreso')->whereDate('created_at',today())->sum('monto')
            - CajaMovimiento::where('ajuste_venta',true)->where('tipo','egreso')->whereDate('created_at',today())->sum('monto');
        $llegadas = Reserva::with('usuario','espacio')->where('estado','confirmada')->whereDoesntHave('estadia')->whereNull('inasistencia_at')->orderBy('fecha_reserva')->orderBy('hora_inicio')->limit(5)->get();
        return response()->view('admin.dashboard',compact('espacios','disponibilidadPorEspacio','libres','activas','pendientes','cobros','llegadas'))->header('Cache-Control','no-store');
    }

    public function estadoMapa(ReservaService $reservas, ReservaDisponibilidadService $disponibilidad, MapaOperativoService $mapa)
    {
        $reservas->procesarReservasAutomaticas();
        $espacios = Espacio::conEstadoOperativo()->with(['sensor', 'vehiculoTipos.tarifas',
            'estadias' => fn ($q) => $q->with('abono')->activas(),
        ])->where('activo', true)->orderBy('codigo')->get();
        $estados = $mapa->estados($espacios, $disponibilidad);
        return response()->json(['espacios' => $espacios->map(fn ($espacio) => [
            'id' => $espacio->id, ...$estados[$espacio->id],
        ])])->header('Cache-Control', 'no-store');
    }

    public function resumen(\Illuminate\Http\Request $request)
    {
        $filters = $request->validate([
            'periodo' => 'nullable|in:dia,mes', 'modo' => 'nullable|in:dia,rango',
            'fecha' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'desde' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde|before_or_equal:today',
            'turno' => 'nullable|in:todos,manana,tarde,noche',
            'grafico' => 'nullable|in:ventas,ingreso,salidas',
        ]);
        $monthly = $request->user()->esSuperAdmin() && ($filters['periodo'] ?? 'dia') === 'mes';
        $mode = ($filters['modo'] ?? 'dia') === 'rango' ? 'rango' : 'dia';
        if ($monthly) {
            $from = \Carbon\Carbon::parse($filters['fecha'] ?? now()->toDateString(), config('app.timezone'))->startOfMonth();
            $to = $from->copy()->endOfMonth();
            if ($to->isFuture()) $to = now();
            $mode = 'rango';
        } elseif ($mode === 'rango') {
            $request->validate(['desde' => 'required|date_format:Y-m-d', 'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde']);
            $from = \Carbon\Carbon::parse($filters['desde'], config('app.timezone'));
            $to = \Carbon\Carbon::parse($filters['hasta'], config('app.timezone'));
        } else {
            $from = \Carbon\Carbon::parse($filters['fecha'] ?? now()->toDateString(), config('app.timezone'));
            $to = $from->copy();
        }
        if (! $request->user()->esSuperAdmin() && (! $from->isToday() || ! $to->isToday())) abort(403);
        $shift = $filters['turno'] ?? 'todos';
        $chart = $filters['grafico'] ?? 'ventas';
        app(\App\Services\VentaService::class)->sincronizar();
        $summary = app(\App\Services\ActivityDashboardService::class)->summarize($from, $to, $shift, $request->user());
        $values = collect($summary['rows'])->pluck($chart);
        $hasActivity = $values->contains(fn ($value) => (float) $value !== 0.0);
        $peak = $hasActivity ? collect($summary['rows'])->first(fn ($row) => $row[$chart] == $values->max())['label'] : null;
        $quiet = $hasActivity ? collect($summary['rows'])->first(fn ($row) => $row[$chart] == $values->min())['label'] : null;
        return response()->view('admin.resumen', compact('summary', 'mode', 'shift', 'chart', 'peak', 'quiet', 'monthly'))
            ->header('Cache-Control', 'no-store');
    }
}
