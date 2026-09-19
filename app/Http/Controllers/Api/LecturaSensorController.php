<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Services\LecturaSensorService;
use Illuminate\Http\Request;

class LecturaSensorController extends Controller
{
    public function store(Request $request, Sensor $sensor, LecturaSensorService $service)
    {
        $token = $request->bearerToken();
        abort_unless($token && $sensor->integracion_iot && $sensor->token_hash && hash_equals($sensor->token_hash, hash('sha256', $token)), 401, 'Credencial de sensor inválida.');
        $data = $request->validate(['evento_id' => 'required|uuid', 'distancia_cm' => 'present|nullable|numeric|between:0,10000|decimal:0,2']);

        return response()->json($service->registrar($sensor, $data, $token));
    }
}
