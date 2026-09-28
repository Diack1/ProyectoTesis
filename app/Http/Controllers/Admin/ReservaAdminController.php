<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reembolso;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservaAdminController extends Controller
{
    public function index(Request $request)
    {
        app(ReservaService::class)->procesarReservasAutomaticas();
        $request->validate(['vista'=>'nullable|in:hoy,proximas,historial','buscar'=>'nullable|string|max:100','estado'=>'nullable|string|max:40']);
        $vista = $request->input('vista', 'hoy');
        $query = Reserva::with([
            'estadia',
            'usuario',
            'espacio',
            'pagos',
            'reembolsos',
        ]);

        if ($vista !== 'historial') {
            $query->whereDoesntHave('estadia')->whereNull('inasistencia_at')->whereIn('estado', ['pendiente_pago','confirmada']);
            $query->whereDate('fecha_reserva', $vista === 'hoy' ? '=' : '>', today());
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_reserva', 'like', "%{$buscar}%")->orWhere('placa', 'like', "%{$buscar}%")
                    ->orWhereHas('usuario', function ($userQuery) use ($buscar) {
                        $userQuery->where('name', 'like', "%{$buscar}%")
                            ->orWhere('email', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('espacio', function ($espacioQuery) use ($buscar) {
                        $espacioQuery->where('codigo', 'like', "%{$buscar}%");
                    });
            });
        }

        $reservas = $query
            ->orderBy('fecha_reserva', $vista === 'historial' ? 'desc' : 'asc')->orderBy('hora_inicio')
            ->paginate(15)
            ->withQueryString();

        $totalReservas = Reserva::count();
        $pendientesPago = Reserva::where('estado', 'pendiente_pago')->count();
        $confirmadas = Reserva::where('estado', 'confirmada')->count();
        $reembolsosSolicitados = Reserva::where('estado', 'reembolso_solicitado')->count();

        return view('admin.reservas.index', compact(
            'reservas',
            'totalReservas',
            'pendientesPago',
            'confirmadas',
            'reembolsosSolicitados'
        ));
    }

    public function aprobarReembolso(Reembolso $reembolso)
    {
        if ($reembolso->estado !== 'solicitado') {
            return redirect()
                ->route('admin.reservas.index')
                ->with('error', 'Este reembolso ya fue procesado.');
        }

        DB::transaction(function () use ($reembolso) {
            Reserva::lockForUpdate()->findOrFail($reembolso->reserva_id);
            $reembolso = Reembolso::lockForUpdate()->findOrFail($reembolso->id);
            abort_unless($reembolso->estado === 'solicitado', 409, 'Este reembolso ya fue procesado.');
            $reembolso->update([
                'estado' => 'aprobado',
                'procesado_at' => now(),
                'procesado_por' => Auth::id(),
            ]);

            if ($reembolso->pago) {
                $reembolso->pago->update([
                    'estado' => 'reembolsado',
                ]);
            }

            if ($reembolso->reserva) {
                $reembolso->reserva->update([
                    'estado' => 'reembolso_aprobado',
                ]);
            }
        });

        return redirect()
            ->route('admin.reservas.index')
            ->with('success', 'Reembolso aprobado correctamente.');
    }

    public function rechazarReembolso(Reembolso $reembolso)
    {
        if ($reembolso->estado !== 'solicitado') {
            return redirect()
                ->route('admin.reservas.index')
                ->with('error', 'Este reembolso ya fue procesado.');
        }

        DB::transaction(function () use ($reembolso) {
            Reserva::lockForUpdate()->findOrFail($reembolso->reserva_id);
            $reembolso = Reembolso::lockForUpdate()->findOrFail($reembolso->id);
            abort_unless($reembolso->estado === 'solicitado', 409, 'Este reembolso ya fue procesado.');
            $reembolso->update([
                'estado' => 'rechazado',
                'procesado_at' => now(),
                'procesado_por' => Auth::id(),
            ]);

            if ($reembolso->reserva) {
                $reembolso->reserva->update([
                    'estado' => 'reembolso_rechazado',
                ]);
            }
        });

        return redirect()
            ->route('admin.reservas.index')
            ->with('success', 'Reembolso rechazado correctamente.');
    }
}
