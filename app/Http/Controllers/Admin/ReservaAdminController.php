<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Reembolso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservaAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Reserva::with([
            'usuario',
            'espacio',
            'pagos',
            'reembolsos',
        ]);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_reserva', 'like', "%{$buscar}%")
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
            ->orderBy('created_at', 'desc')
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
