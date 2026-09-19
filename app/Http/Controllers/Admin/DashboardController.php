<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Espacio, Estadia, Pago, Reserva};
use App\Services\{ReservaDisponibilidadService, ReservaService};

class DashboardController extends Controller
{
    public function index(ReservaService $reservas, ReservaDisponibilidadService $disponibilidad)
    {
        $reservas->procesarReservasAutomaticas();
        $espacios = Espacio::with(['sensor','vehiculoTipos.tarifas',
            'estadias' => fn ($q) => $q->activas(),
            'reservas' => fn ($q) => $q->with('usuario')->whereIn('estado',['pendiente_pago','confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia'),
        ])->where('activo',true)->orderBy('codigo')->get();
        $disponibilidadPorEspacio = $espacios->mapWithKeys(fn ($e) => [$e->id => $disponibilidad->estadoParaTarjeta($e)]);
        $libres = $espacios->where('estado_actual','libre')->count();
        $activas = Estadia::activas()->count();
        $pendientes = Pago::where('estado','pendiente')->whereNotNull('enviado_at')->count();
        $cobros = Pago::where('estado','aprobado')->whereDate('pagado_at',today())->sum('monto');
        $llegadas = Reserva::with('usuario','espacio')->where('estado','confirmada')->whereDoesntHave('estadia')->whereNull('inasistencia_at')->orderBy('fecha_reserva')->orderBy('hora_inicio')->limit(5)->get();
        return response()->view('admin.dashboard',compact('espacios','disponibilidadPorEspacio','libres','activas','pendientes','cobros','llegadas'))->header('Cache-Control','no-store');
    }
}
