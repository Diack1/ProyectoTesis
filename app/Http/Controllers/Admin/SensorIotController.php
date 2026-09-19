<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SensorIotController extends Controller
{
    public function index()
    {
        return response()->view('admin.sensores.index', ['sensores' => Sensor::with('espacio')->orderBy('codigo_sensor')->get()])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Sensor $sensor)
    {
        $data = $request->validate(['umbral_ocupado_cm' => 'required|numeric|min:1|max:9998', 'umbral_libre_cm' => 'required|numeric|gt:umbral_ocupado_cm|max:9999',
            'distancia_min_cm' => 'required|numeric|min:0.1|lte:umbral_ocupado_cm', 'distancia_max_cm' => 'required|numeric|gte:umbral_libre_cm|max:10000',
            'lecturas_confirmacion' => 'required|integer|min:2|max:10', 'segundos_sin_senal' => 'required|integer|min:15|max:300', 'estado' => 'required|in:activo,inactivo']);
        DB::transaction(function () use ($sensor, $data) {
            $space = Espacio::lockForUpdate()->findOrFail($sensor->espacio_id);
            abort_if($space->estadias()->activas()->exists(), 409, 'Finaliza el ticket antes de recalibrar el sensor.');
            $sensor = Sensor::lockForUpdate()->findOrFail($sensor->id);
            $sensor->forceFill($data + ['estado_estable' => null, 'estado_candidato' => null, 'cantidad_candidata' => 0, 'ultima_lectura_valida_at' => null])->save();
        });

        return back()->with('success', 'Calibración guardada. Se requieren nuevas lecturas estables antes de ofrecer el espacio con sensor.');
    }

    public function token(Request $request, Sensor $sensor)
    {
        $token = Str::random(64);
        DB::transaction(function () use ($sensor, $token) {
            $space = Espacio::lockForUpdate()->findOrFail($sensor->espacio_id);
            abort_if($space->estadias()->activas()->exists(), 409, 'Finaliza el ticket antes de renovar la credencial.');
            $sensor = Sensor::lockForUpdate()->findOrFail($sensor->id);
            $sensor->forceFill(['token_hash' => hash('sha256', $token), 'integracion_iot' => true, 'estado_estable' => null, 'estado_candidato' => null, 'cantidad_candidata' => 0, 'ultima_lectura_valida_at' => null])->save();
        });

        return back()->with('sensor_token', $token)->with('sensor_codigo', $sensor->codigo_sensor)->with('success','Credencial generada. Cópiala a la configuración de ese sensor; la anterior dejó de funcionar.');
    }
}
