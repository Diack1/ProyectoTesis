<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\Abono;
use App\Models\Reserva;
use App\Models\VehiculoTipo;
use App\Models\Tarifa;
use App\Services\CorreccionEstadiaService;
use App\Services\LiquidacionService;
use App\Services\TarifaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EspacioOperacionController extends Controller
{
    public function show(Request $request, Espacio $espacio, LiquidacionService $liquidaciones, TarifaService $tarifasService)
    {
        $request->validate(['vista' => 'nullable|in:cobrar,ticket,editar,mover,historial,ingreso,reserva,estado,comprobante',
            'ticket' => 'nullable|integer|exists:estadias,id']);
        $espacio->load('sensor', 'vehiculoTipos');
        $estadia = Estadia::with('espacio', 'vehiculoTipo', 'reserva.usuario', 'operadorIngreso', 'clienteCobro', 'pagosSalida',
            'reubicaciones.origen', 'reubicaciones.destino', 'reubicaciones.operador', 'auditorias.usuario')
            ->activas()->where('espacio_id', $espacio->id)->first();
        $finalizada = false;
        if (!$estadia && $request->filled('ticket')) {
            $estadia = Estadia::with('espacio', 'vehiculoTipo', 'reserva.usuario', 'operadorIngreso', 'operadorSalida',
                'clienteCobro', 'pagosSalida', 'reubicaciones.origen', 'reubicaciones.destino', 'reubicaciones.operador', 'auditorias.usuario')
                ->where('espacio_id', $espacio->id)->whereNotNull('hora_salida')->findOrFail($request->integer('ticket'));
            $finalizada = true;
        }
        $reserva = $estadia ? null : Reserva::with('usuario', 'pagos')->where('espacio_id', $espacio->id)
            ->whereIn('estado', ['pendiente_pago', 'confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia')->first();
        $libre = $espacio->activo && $espacio->estado_actual === 'libre';
        $vista = $request->input('vista', $finalizada ? 'comprobante' : ($estadia ? 'ticket' : ($reserva ? 'reserva' : ($libre ? 'ingreso' : 'estado'))));
        $permitidas = $finalizada ? ['comprobante', 'ticket', 'historial'] : ($estadia ? ['ticket', 'cobrar', 'editar', 'mover', 'historial'] : ($reserva ? ['reserva'] : ($libre ? ['ingreso'] : ['estado'])));
        if (! in_array($vista, $permitidas, true)) $vista = $permitidas[0];
        $liquidacion = $cotizacion = null;
        if ($estadia && !$finalizada && $vista === 'cobrar' && $estadia->inicio_cobro) {
            $corte = now();
            $liquidacion = $liquidaciones->calcular($estadia, $corte);
            $cotizacion = Crypt::encryptString(json_encode(['id' => $estadia->id, 'inicio' => $estadia->inicio_cobro->toIso8601String(), 'corte' => $corte->toIso8601String()]));
        }
        $destinos = collect();
        if ($estadia && !$finalizada && $vista === 'mover') {
            $destinos = Espacio::conEstadoOperativo()->with('sensor', 'vehiculoTipos')->where('activo', true)
                ->orderBy('codigo')->get()
                ->filter(fn ($e) => $e->id !== $espacio->id && $e->estado_actual === 'libre'
                    && $e->vehiculoTipos->contains('id', $estadia->vehiculo_tipo_id)
                    && ($e->modo_monitoreo !== 'sensor' || ($e->sensor?->lectura_vigente && $e->sensor?->integracion_iot)));
        }
        $tarifasIngreso = $tarifasService->tarifasIngreso()->whereIn('vehiculo_tipo_id', $espacio->vehiculoTipos->pluck('id'));
        $ventaComprobante = $finalizada && $vista === 'comprobante'
            ? \App\Models\Venta::with('pagos')->where('origen_tipo','estadia')->where('origen_id',$estadia->id)
                ->whereIn('estado',['vigente','corregida'])->latest('version')->first()
            : null;
        return response()->view('admin.espacios.operacion', compact('espacio', 'estadia', 'reserva', 'vista', 'liquidacion', 'cotizacion', 'destinos', 'finalizada')
            + ['tipos' => VehiculoTipo::where('activo', true)->get(),
                'ventaComprobante' => $ventaComprobante,
                'tarifasEdicion' => $tarifasIngreso, 'tarifasIngreso' => $tarifasIngreso,
                'casilleros' => app(\App\Services\CasilleroService::class)->opciones(),
                'abonos' => Abono::vigentes()->with('abonado', 'espacio')->where('espacio_id', $espacio->id)->get()])
            ->header('Cache-Control', 'no-store');
    }

    public function corregir(Request $request, Estadia $estadia, CorreccionEstadiaService $service)
    {
        $request->merge(['placa' => strtoupper(preg_replace('/[\s-]+/', '', (string) $request->input('placa')))]);
        $data = $request->validate(['placa' => 'required|regex:/^[A-Z0-9]{5,10}$/',
            'vehiculo_tipo_id' => 'nullable|integer|exists:vehiculo_tipos,id', 'tarifa_id' => 'nullable|integer|exists:tarifas,id',
            'motivo' => 'required|string|min:5|max:500', 'correccion_confirmada' => 'required|accepted']);
        $service->corregir($estadia, $data, $request->user());
        return redirect()->route('admin.espacios.operar', ['espacio' => $estadia->fresh()->espacio_id, 'vista' => 'historial'])
            ->with('success', 'Corrección guardada en el historial de auditoría.');
    }
}
