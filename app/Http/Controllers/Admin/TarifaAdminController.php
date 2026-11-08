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
        $query = Tarifa::with('vehiculoTipo')->whereIn('tipo_tarifa', ['por_hora', 'bloque_horas', 'mensual']);

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
        $data = $this->validarTarifa($request, $tarifa);

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
        if ($tarifa->tipo_tarifa === 'bloque_horas' && !Tarifa::where('vehiculo_tipo_id',$tarifa->vehiculo_tipo_id)
            ->where('tipo_tarifa','por_hora')->where('activo',true)->where('minutos_fraccion','>',0)->where('monto_por_fraccion','>',0)->exists()) {
            throw ValidationException::withMessages(['tarifa'=>'Activa primero la tarifa por hora de este vehículo.']);
        }
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
        if ($tarifa->tipo_tarifa === 'por_hora' && Tarifa::where('vehiculo_tipo_id',$tarifa->vehiculo_tipo_id)
            ->where('tipo_tarifa','bloque_horas')->where('activo',true)->exists()) {
            throw ValidationException::withMessages(['tarifa'=>'Desactiva primero la tarifa por bloque de este vehículo.']);
        }
        $tarifa->update([
            'activo' => false,
        ]);

        return redirect()
            ->route('admin.tarifas.index')
            ->with('success', 'Tarifa desactivada correctamente.');
    }

    private function validarTarifa(Request $request, ?Tarifa $actual = null): array
    {
        $data = $request->validate([
            'vehiculo_tipo_id' => 'required|integer|exists:vehiculo_tipos,id',
            'tipo_tarifa' => ['required', Rule::in(['por_hora', 'bloque_horas', 'mensual'])],
            'precio' => 'required|numeric|gt:0|max:9999|decimal:0,2',
            'horas_bloque' => 'required_if:tipo_tarifa,bloque_horas|nullable|integer|min:2|max:720',
            'minutos_fraccion' => 'required_if:tipo_tarifa,por_hora|nullable|integer|min:1|max:60',
            'monto_por_fraccion' => 'required_if:tipo_tarifa,por_hora|nullable|numeric|gt:0|max:9999|decimal:0,2',
            'tolerancia_minutos' => 'nullable|integer|min:0|max:120',
            'activo' => 'nullable|boolean',
        ]);
        $tipo = VehiculoTipo::where('activo', true)->findOrFail($data['vehiculo_tipo_id']);
        if (!$actual && Tarifa::where('vehiculo_tipo_id', $tipo->id)->where('tipo_tarifa', $data['tipo_tarifa'])
            ->where(fn ($q)=>$q->where('activo',true)->orWhere('cobro_intervalos',true))->exists()) {
            throw ValidationException::withMessages(['tipo_tarifa' => 'Ya existe esta tarifa para el vehículo. Edítala en la lista.']);
        }
        if ($data['tipo_tarifa'] === 'bloque_horas' && !Tarifa::where('vehiculo_tipo_id', $tipo->id)
            ->where('tipo_tarifa', 'por_hora')->where('activo', true)->where('minutos_fraccion','>',0)->where('monto_por_fraccion','>',0)->exists()) {
            throw ValidationException::withMessages(['tipo_tarifa' => 'Configura y activa primero la tarifa por hora con sus intervalos adicionales.']);
        }
        if ($actual?->tipo_tarifa === 'por_hora' && (!$request->boolean('activo') || $tipo->id !== $actual->vehiculo_tipo_id)
            && Tarifa::where('vehiculo_tipo_id',$actual->vehiculo_tipo_id)->where('tipo_tarifa','bloque_horas')->where('activo',true)->exists()) {
            throw ValidationException::withMessages(['activo'=>'Desactiva primero la tarifa por bloque de este vehículo.']);
        }
        $nombre = match ($data['tipo_tarifa']) {
            'por_hora' => $tipo->nombre.' · 1 hora',
            'bloque_horas' => $tipo->nombre.' · '.$data['horas_bloque'].' horas',
            'mensual' => $tipo->nombre.' · mensual',
        };
        $hora = $data['tipo_tarifa'] === 'por_hora';
        return [
            'vehiculo_tipo_id' => $tipo->id, 'nombre' => $nombre, 'tipo_tarifa' => $data['tipo_tarifa'],
            'monto_base' => $hora ? 0 : $data['precio'], 'monto_por_hora' => $hora ? $data['precio'] : 0,
            'monto_por_fraccion' => $hora ? $data['monto_por_fraccion'] : null,
            'minutos_fraccion' => $hora ? $data['minutos_fraccion'] : null,
            'tiempo_minimo_minutos' => $data['tipo_tarifa'] === 'bloque_horas' ? $data['horas_bloque'] * 60 : 60,
            'tolerancia_minutos' => $hora ? ($data['tolerancia_minutos'] ?? 0) : 0,
            'penalidad_por_fraccion' => $hora ? $data['monto_por_fraccion'] : 0,
            'hora_inicio' => null, 'hora_fin' => null, 'prioridad' => 1,
            'activo' => $request->boolean('activo'),
            'cobro_intervalos' => true,
        ];
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
