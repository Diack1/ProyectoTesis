<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\RegistroOcupacion;
use App\Services\ReservaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonitoreoController extends Controller
{
    public function index()
    {
        app(ReservaService::class)->procesarReservasAutomaticas();
        $espacios = Espacio::with(['sensor', 'estadias' => fn ($q) => $q->activas()])
            ->orderBy('codigo')
            ->get();

        $totalEspacios = $espacios->count();
        $espaciosLibres = $espacios->where('estado_actual', 'libre')->count();
        $espaciosOcupados = $espacios->where('estado_actual', 'ocupado')->count();
        $espaciosReservados = $espacios->where('estado_actual', 'reservado')->count();
        $espaciosMantenimiento = $espacios->where('estado_actual', 'mantenimiento')->count();

        $registros = RegistroOcupacion::with(['espacio', 'sensor'])
            ->orderBy('fecha_hora', 'desc')
            ->take(10)
            ->get();

        return view('monitoreo.index', compact(
            'espacios',
            'totalEspacios',
            'espaciosLibres',
            'espaciosOcupados',
            'registros',
            'espaciosReservados',
            'espaciosMantenimiento'
        ));
    }

    public function cambiarEstado(Request $request, Espacio $espacio)
    {
        $request->validate([
            'estado' => 'required|in:libre,ocupado,mantenimiento',
        ]);

        DB::transaction(function () use ($request, $espacio) {
            $espacio = Espacio::lockForUpdate()->findOrFail($espacio->id);
            if ($espacio->estadias()->activas()->exists()) {
                throw ValidationException::withMessages(['estado' => 'Este espacio tiene un ticket activo. Registra la salida desde el ticket.']);
            }
            $espacio->update(['estado_actual' => $request->estado]);
            RegistroOcupacion::create(['espacio_id' => $espacio->id, 'sensor_id' => $espacio->sensor?->id,
                'estado_detectado' => $request->estado, 'fecha_hora' => now(), 'origen' => 'manual_admin']);
        });

        return redirect()
            ->route('admin.monitoreo.index')
            ->with('success', 'Estado del espacio actualizado correctamente.');
    }
}
