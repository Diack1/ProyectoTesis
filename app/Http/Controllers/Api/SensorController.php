<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\RegistroOcupacion;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SensorController extends Controller
{
    public function registrarOcupacion(Request $request)
    {
        abort_unless($request->user()?->activo && $request->user()->tieneRol('admin', 'super_admin', 'operador'), 403);
        $request->validate([
            'codigo_sensor' => 'required|string|exists:sensores,codigo_sensor',
            'estado' => 'required|in:libre,ocupado',
            'distancia_cm' => 'nullable|numeric',
        ]);

        $sensor = Sensor::with('espacio')
            ->where('codigo_sensor', $request->codigo_sensor)
            ->first();

        if (! $sensor || ! $sensor->espacio) {
            return response()->json([
                'success' => false,
                'message' => 'Sensor no asociado a ningún espacio.',
            ], 404);
        }

        abort_if($sensor->integracion_iot, 409, 'Este sensor debe enviar distancias a su dirección de recepción IoT.');
        $espacio = $sensor->espacio;
        abort_unless($espacio->modo_monitoreo === 'sensor' && $sensor->estado === 'activo', 409, 'Este espacio está bajo control manual o el sensor está inactivo.');

        if ($espacio->estado_actual === 'mantenimiento') {
            return response()->json([
                'success' => false,
                'message' => 'El espacio está en mantenimiento y no puede ser actualizado por el sensor.',
                'data' => [
                    'espacio' => $espacio->codigo,
                    'estado_actual' => $espacio->estado_actual,
                ],
            ], 409);
        }

        $registro = DB::transaction(function () use ($request, $sensor) {
            $espacio = Espacio::lockForUpdate()->findOrFail($sensor->espacio_id);
            abort_unless($espacio->modo_monitoreo === 'sensor' && $espacio->getRawOriginal('estado_actual') !== 'mantenimiento', 409);
            abort_if(Sensor::findOrFail($sensor->id)->integracion_iot, 409);
            $espacio->update(['estado_actual' => $request->estado]);
            if ($request->estado === 'ocupado') {
                $estadia = Estadia::activas()->where('espacio_id', $espacio->id)->lockForUpdate()->first();
                if ($estadia && ! $estadia->inicio_cobro) {
                    $estadia->update(['inicio_cobro' => now(), 'fuente_inicio' => 'sensor']);
                }
            }

            return RegistroOcupacion::create([
                'espacio_id' => $espacio->id, 'sensor_id' => $sensor->id, 'estado_detectado' => $request->estado,
                'distancia_cm' => $request->distancia_cm, 'fecha_hora' => now(),
                'origen' => $sensor->tipo_sensor === 'simulado' ? 'api_simulada' : 'sensor_iot',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Estado registrado correctamente.',
            'data' => [
                'espacio' => $espacio->codigo,
                'sensor' => $sensor->codigo_sensor,
                'estado' => $request->estado,
                'distancia_cm' => $request->distancia_cm,
                'fecha_hora' => $registro->fecha_hora,
            ],
        ], 201);
    }

    public function estadoActual()
    {
        $espacios = Espacio::with('sensor')
            ->orderBy('codigo')
            ->get();

        $registros = RegistroOcupacion::with(['espacio', 'sensor'])
            ->orderBy('fecha_hora', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'resumen' => [
                'total' => $espacios->count(),
                'libres' => $espacios->where('estado_actual', 'libre')->count(),
                'ocupados' => $espacios->where('estado_actual', 'ocupado')->count(),
                'reservados' => $espacios->where('estado_actual', 'reservado')->count(),
                'mantenimiento' => $espacios->where('estado_actual', 'mantenimiento')->count(),
            ],
            'espacios' => $espacios->map(function ($espacio) {
                return [
                    'id' => $espacio->id,
                    'codigo' => $espacio->codigo,
                    'descripcion' => $espacio->descripcion,
                    'estado_actual' => $espacio->estado_actual,
                    'sensor' => $espacio->sensor ? $espacio->sensor->codigo_sensor : 'Sin sensor',
                ];
            }),
            'registros' => $registros->map(function ($registro) {
                return [
                    'fecha_hora' => $registro->fecha_hora,
                    'espacio' => $registro->espacio->codigo ?? '-',
                    'sensor' => $registro->sensor->codigo_sensor ?? '-',
                    'estado_detectado' => $registro->estado_detectado,
                    'distancia_cm' => $registro->distancia_cm,
                    'origen' => $registro->origen,
                ];
            }),
        ]);
    }
}
