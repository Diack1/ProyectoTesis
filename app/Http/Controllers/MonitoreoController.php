<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\RegistroOcupacion;
use Illuminate\Http\Request;

class MonitoreoController extends Controller
{
    public function index()
    {
        $espacios = Espacio::with('sensor')
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
            'estado' => 'required|in:libre,ocupado,reservado,mantenimiento',
        ]);

        $sensor = $espacio->sensor;

        $espacio->update([
            'estado_actual' => $request->estado,
        ]);

        RegistroOcupacion::create([
            'espacio_id' => $espacio->id,
            'sensor_id' => $sensor ? $sensor->id : null,
            'estado_detectado' => $request->estado,
            'distancia_cm' => match ($request->estado) {
                'ocupado' => 8,
                'libre' => 30,
                default => null,
            },
            'fecha_hora' => now(),
            'origen' => 'manual_admin',
        ]);

        return redirect()
            ->route('admin.monitoreo.index')
            ->with('success', 'Estado del espacio actualizado correctamente.');
    }
}