<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Services\{ReservaDisponibilidadService, TarifaService};
use Illuminate\Http\Request;

class PublicQuoteController extends Controller
{
    public function __invoke(Request $request, Espacio $espacio, TarifaService $tarifas, ReservaDisponibilidadService $disponibilidad)
    {
        $data = $request->validate([
            'vehiculo_tipo_id' => 'required|integer',
            'duracion_minutos' => 'required|integer|in:60,120,180,240',
        ]);
        abort_unless($espacio->activo, 404);
        $tipo = $espacio->vehiculoTipos()->where('vehiculo_tipos.activo', true)->find($data['vehiculo_tipo_id']);
        if (!$tipo) {
            return response()->json(['message' => 'Este espacio no admite el vehículo seleccionado.'], 422);
        }
        try {
            $calculo = $tarifas->calcularMonto($tipo, now('America/Lima')->startOfMinute(), (int)$data['duracion_minutos']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'No hay una tarifa vigente para esta selección.'], 422);
        }
        return response()->json([
            ...\App\Services\TarifaResumen::presentar($calculo),
            'tarifa' => $calculo['tarifa']->nombre,
            'total' => number_format($calculo['monto_total'], 2, '.', ''),
            'minutos_cobro' => $calculo['minutos_cobro'],
            'puede_reservar' => $disponibilidad->estadoParaTarjeta($espacio)['puede_reservar'],
            'continuar' => route('reservas.create', ['espacio' => $espacio->id, ...$data], false),
        ])->header('Cache-Control', 'no-store');
    }
}
