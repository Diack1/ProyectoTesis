<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tarifa;
use App\Models\VehiculoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TarifaAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Tarifa::with('vehiculoTipo');

        if ($request->filled('vehiculo_tipo_id')) {
            $query->where('vehiculo_tipo_id', $request->vehiculo_tipo_id);
        }

        if ($request->filled('tipo_tarifa')) {
            $query->where('tipo_tarifa', $request->tipo_tarifa);
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        $tarifas = $query
            ->orderByDesc('activo')
            ->orderByDesc('prioridad')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $vehiculoTipos = VehiculoTipo::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('admin.tarifas.index', compact('tarifas', 'vehiculoTipos'));
    }

    public function create()
    {
        $vehiculoTipos = VehiculoTipo::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('admin.tarifas.create', compact('vehiculoTipos'));
    }

    public function store(Request $request)
    {
        $data = $this->validarTarifa($request);

        DB::transaction(function () use ($data) {
            if ($data['activo']) {
                $this->desactivarTarifasConflictivas($data);
            }

            Tarifa::create($data);
        });

        return redirect()
            ->route('admin.tarifas.index')
            ->with('success', 'Tarifa creada correctamente.');
    }

    public function edit(Tarifa $tarifa)
    {
        $vehiculoTipos = VehiculoTipo::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('admin.tarifas.edit', compact('tarifa', 'vehiculoTipos'));
    }

    public function update(Request $request, Tarifa $tarifa)
    {
        $data = $this->validarTarifa($request);

        DB::transaction(function () use ($data, $tarifa) {
            if ($data['activo']) {
                $this->desactivarTarifasConflictivas($data, $tarifa);
            }

            $tarifa->update($data);
        });

        return redirect()
            ->route('admin.tarifas.index')
            ->with('success', 'Tarifa actualizada correctamente.');
    }

    public function activar(Tarifa $tarifa)
    {
        DB::transaction(function () use ($tarifa) {
            $this->desactivarTarifasConflictivas([
                'vehiculo_tipo_id' => $tarifa->vehiculo_tipo_id,
                'tipo_tarifa' => $tarifa->tipo_tarifa,
            ], $tarifa);

            $tarifa->update([
                'activo' => true,
            ]);
        });

        return redirect()
            ->route('admin.tarifas.index')
            ->with('success', 'Tarifa activada correctamente.');
    }

    public function desactivar(Tarifa $tarifa)
    {
        $tarifa->update([
            'activo' => false,
        ]);

        return redirect()
            ->route('admin.tarifas.index')
            ->with('success', 'Tarifa desactivada correctamente.');
    }

    private function validarTarifa(Request $request): array
    {
        $data = $request->validate([
            'vehiculo_tipo_id' => 'required|exists:vehiculo_tipos,id',
            'nombre' => 'required|string|max:120',
            'tipo_tarifa' => [
                'required',
                Rule::in(['por_hora', 'fraccion', 'diaria', 'nocturna']),
            ],
            'monto_base' => 'nullable|numeric|min:0|max:9999',
            'monto_por_hora' => 'nullable|numeric|min:0|max:9999',
            'monto_por_fraccion' => 'nullable|numeric|min:0|max:9999',
            'minutos_fraccion' => 'nullable|integer|min:1|max:1440',
            'tiempo_minimo_minutos' => 'required|integer|min:1|max:1440',
            'tolerancia_minutos' => 'required|integer|min:0|max:120',
            'penalidad_por_fraccion' => 'nullable|numeric|min:0|max:9999',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'prioridad' => 'required|integer|min:1|max:100',
            'activo' => 'nullable|boolean',
        ]);

        $data['monto_base'] = $data['monto_base'] ?? 0;
        $data['monto_por_hora'] = $data['monto_por_hora'] ?? 0;
        $data['monto_por_fraccion'] = $data['monto_por_fraccion'] ?? null;
        $data['minutos_fraccion'] = $data['minutos_fraccion'] ?? null;
        $data['penalidad_por_fraccion'] = $data['penalidad_por_fraccion'] ?? 0;
        $data['hora_inicio'] = $data['hora_inicio'] ?? null;
        $data['hora_fin'] = $data['hora_fin'] ?? null;
        $data['activo'] = $request->boolean('activo');

        if ($data['tipo_tarifa'] === 'por_hora' && $data['monto_por_hora'] <= 0) {
            throw ValidationException::withMessages([
                'monto_por_hora' => 'Para una tarifa por hora, el monto por hora debe ser mayor a 0.',
            ]);
        }

        if ($data['tipo_tarifa'] === 'fraccion') {
            if (!$data['monto_por_fraccion'] || !$data['minutos_fraccion']) {
                throw ValidationException::withMessages([
                    'monto_por_fraccion' => 'Para tarifa por fracción, debes ingresar monto por fracción y minutos por fracción.',
                ]);
            }
        }

        if ($data['tipo_tarifa'] === 'diaria' && $data['monto_base'] <= 0) {
            throw ValidationException::withMessages([
                'monto_base' => 'Para una tarifa diaria, el monto base debe ser mayor a 0.',
            ]);
        }

        if ($data['tipo_tarifa'] === 'nocturna') {
            if (!$data['hora_inicio'] || !$data['hora_fin']) {
                throw ValidationException::withMessages([
                    'hora_inicio' => 'Para tarifa nocturna, debes ingresar hora de inicio y hora de fin.',
                ]);
            }

            if ($data['monto_base'] <= 0 && $data['monto_por_hora'] <= 0) {
                throw ValidationException::withMessages([
                    'monto_base' => 'La tarifa nocturna debe tener monto base o monto por hora.',
                ]);
            }
        }

        return $data;
    }

    private function desactivarTarifasConflictivas(array $data, ?Tarifa $excepto = null): void
    {
        $query = Tarifa::where('vehiculo_tipo_id', $data['vehiculo_tipo_id'])
            ->where('tipo_tarifa', $data['tipo_tarifa'])
            ->where('activo', true);

        if ($excepto) {
            $query->where('id', '!=', $excepto->id);
        }

        $query->update([
            'activo' => false,
        ]);
    }
}
