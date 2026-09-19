<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\VehiculoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EspacioController extends Controller
{
    public function index()
    {
        return view('espacios.index', ['espacios' => Espacio::with('sensor', 'vehiculoTipos')->orderBy('codigo')->paginate(30)]);
    }

    public function create()
    {
        return view('espacios.create', ['vehiculoTipos' => VehiculoTipo::where('activo', true)->orderBy('nombre')->get()]);
    }

    public function edit(Espacio $espacio)
    {
        return view('espacios.edit', ['espacio' => $espacio->load('sensor', 'vehiculoTipos'), 'vehiculoTipos' => VehiculoTipo::where('activo', true)->orderBy('nombre')->get()]);
    }

    private function guardar(Request $request, Espacio $espacio): void
    {
        $sensor = $espacio->sensor;
        $request->merge(['codigo' => strtoupper((string) $request->codigo), 'codigo_sensor' => $request->filled('codigo_sensor') ? strtoupper($request->codigo_sensor) : null]);
        $data = $request->validate([
            'codigo' => 'required|string|max:20|unique:espacios,codigo,'.($espacio->id ?? 'NULL'),
            'descripcion' => 'nullable|string|max:255', 'estado_actual' => 'required|in:libre,ocupado,mantenimiento',
            'modo_monitoreo' => 'required|in:manual,sensor', 'incluido_estudio' => 'sometimes|boolean',
            'codigo_sensor' => 'nullable|required_if:modo_monitoreo,sensor|string|max:50|unique:sensores,codigo_sensor,'.($sensor?->id ?? 'NULL'),
            'tipo_sensor' => 'nullable|required_with:codigo_sensor|string|max:50',
            'vehiculo_tipo_ids' => 'required|array|min:1', 'vehiculo_tipo_ids.*' => 'exists:vehiculo_tipos,id',
        ]);
        DB::transaction(function () use ($request, $espacio, $data) {
            if ($espacio->exists) {
                $espacio = Espacio::lockForUpdate()->findOrFail($espacio->id);
                if ($espacio->estadias()->activas()->exists()) {
                    throw ValidationException::withMessages(['estado_actual' => 'Finaliza el ticket activo antes de cambiar la configuración del espacio.']);
                }
            }
            if ($data['modo_monitoreo'] === 'sensor' && $espacio->sensor?->integracion_iot) {
                $sensorActual = $espacio->sensor;
                if (! $sensorActual->lectura_vigente || $sensorActual->estado !== 'activo' || $data['codigo_sensor'] !== $sensorActual->codigo_sensor || $data['tipo_sensor'] !== $sensorActual->tipo_sensor) {
                    throw ValidationException::withMessages(['modo_monitoreo' => 'Primero verifica la conexión y calibración del sensor actual en Sensores IoT.']);
                }
                if ($data['estado_actual'] !== 'mantenimiento') {
                    $data['estado_actual'] = $sensorActual->estado_estable;
                }
            }
            $espacio->fill(collect($data)->only(['codigo', 'descripcion', 'estado_actual', 'modo_monitoreo'])->all());
            $espacio->activo = $request->boolean('activo');
            $espacio->incluido_estudio = $request->boolean('incluido_estudio');
            $espacio->save();
            $espacio->vehiculoTipos()->sync($data['vehiculo_tipo_ids']);
            if ($request->filled('codigo_sensor')) {
                $espacio->sensor()->updateOrCreate(['espacio_id' => $espacio->id], ['codigo_sensor' => $data['codigo_sensor'],
                    'tipo_sensor' => $data['tipo_sensor'], 'estado' => $data['modo_monitoreo'] === 'sensor' ? 'activo' : 'inactivo']);
            } elseif ($espacio->sensor) {
                $espacio->sensor->update(['estado' => 'inactivo']);
            }
        });
    }

    public function store(Request $request)
    {
        $this->guardar($request, new Espacio);

        return redirect()->route('admin.espacios.index')->with('success', 'Espacio creado.');
    }

    public function update(Request $request, Espacio $espacio)
    {
        $this->guardar($request, $espacio);

        return redirect()->route('admin.espacios.index')->with('success', 'Espacio actualizado.');
    }

    public function destroy(Espacio $espacio)
    {
        $espacio->update(['activo' => false]);

        return back()->with('success', 'Espacio desactivado. Su historial se conserva.');
    }
}
