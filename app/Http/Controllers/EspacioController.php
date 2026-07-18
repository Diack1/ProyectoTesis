<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Sensor;
use App\Models\VehiculoTipo;
use Illuminate\Http\Request;

class EspacioController extends Controller
{
    public function index()
    {
        $espacios = Espacio::with(['sensor', 'vehiculoTipos'])
            ->orderBy('codigo')
            ->paginate(10);

        return view('espacios.index', compact('espacios'));
    }

    public function create()
    {
        $vehiculoTipos = VehiculoTipo::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('espacios.create', compact('vehiculoTipos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|max:20|unique:espacios,codigo',
            'descripcion' => 'nullable|string|max:255',
            'estado_actual' => 'required|in:libre,ocupado,reservado,mantenimiento',
            'codigo_sensor' => 'required|string|max:50|unique:sensores,codigo_sensor',
            'tipo_sensor' => 'required|string|max:50',
            'vehiculo_tipo_ids' => 'required|array|min:1',
            'vehiculo_tipo_ids.*' => 'exists:vehiculo_tipos,id',
        ]);

        $espacio = Espacio::create([
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
            'estado_actual' => $request->estado_actual,
            'activo' => $request->has('activo'),
        ]);

        $espacio->vehiculoTipos()->sync($request->vehiculo_tipo_ids);

        Sensor::create([
            'espacio_id' => $espacio->id,
            'codigo_sensor' => strtoupper($request->codigo_sensor),
            'tipo_sensor' => $request->tipo_sensor,
            'estado' => 'activo',
        ]);

        return redirect()
            ->route('admin.espacios.index')
            ->with('success', 'Espacio creado correctamente.');
    }

    public function edit(Espacio $espacio)
    {
        $espacio->load(['sensor', 'vehiculoTipos']);

        $vehiculoTipos = VehiculoTipo::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('espacios.edit', compact('espacio', 'vehiculoTipos'));
    }

    public function update(Request $request, Espacio $espacio)
    {
        $sensor = $espacio->sensor;

        $request->validate([
            'codigo' => 'required|string|max:20|unique:espacios,codigo,' . $espacio->id,
            'descripcion' => 'nullable|string|max:255',
            'estado_actual' => 'required|in:libre,ocupado,reservado,mantenimiento',
            'codigo_sensor' => 'required|string|max:50|unique:sensores,codigo_sensor,' . ($sensor ? $sensor->id : 'NULL'),
            'tipo_sensor' => 'required|string|max:50',
            'vehiculo_tipo_ids' => 'required|array|min:1',
            'vehiculo_tipo_ids.*' => 'exists:vehiculo_tipos,id',
        ]);

        $espacio->update([
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
            'estado_actual' => $request->estado_actual,
            'activo' => $request->has('activo'),
        ]);

        $espacio->vehiculoTipos()->sync($request->vehiculo_tipo_ids);

        if ($sensor) {
            $sensor->update([
                'codigo_sensor' => strtoupper($request->codigo_sensor),
                'tipo_sensor' => $request->tipo_sensor,
                'estado' => $request->has('sensor_activo') ? 'activo' : 'inactivo',
            ]);
        } else {
            Sensor::create([
                'espacio_id' => $espacio->id,
                'codigo_sensor' => strtoupper($request->codigo_sensor),
                'tipo_sensor' => $request->tipo_sensor,
                'estado' => $request->has('sensor_activo') ? 'activo' : 'inactivo',
            ]);
        }

        return redirect()
            ->route('admin.espacios.index')
            ->with('success', 'Espacio actualizado correctamente.');
    }

    public function destroy(Espacio $espacio)
    {
        $espacio->delete();

        return redirect()
            ->route('admin.espacios.index')
            ->with('success', 'Espacio eliminado correctamente.');
    }
}
