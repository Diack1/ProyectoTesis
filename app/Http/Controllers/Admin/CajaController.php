<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CajaApertura, CajaCierre, CajaMovimiento, Pago, Reembolso};
use App\Services\{CajaService, LiquidacionService};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    public function index(CajaService $service)
    {
        $caja = CajaApertura::with('operador')->where('abierta_unica', 1)
            ->when(auth()->user()->role !== 'super_admin', fn ($q) => $q->where('operador_id', auth()->id()))->first();
        return view('admin.caja.index', [
            'caja' => $caja,
            'arqueo' => $caja ? $service->arqueo($caja) : null,
            'cierres' => auth()->user()->role === 'super_admin' ? CajaCierre::with('operador', 'apertura')->orderByDesc('fin_at')->limit(20)->get() : collect(),
            'pagosAnteriores' => auth()->user()->role === 'super_admin' ? Pago::whereNull('caja_apertura_id')->whereNull('caja_cierre_id')
                ->whereIn('estado', ['aprobado', 'reembolsado'])->whereNotNull('pagado_at')
                ->orderByDesc('pagado_at')->limit(50)->get() : collect(),
        ]);
    }

    public function abrir(Request $request)
    {
        $data = $request->validate(['monto_inicial' => 'required|numeric|min:0|max:999999|decimal:0,2']);
        try {
            DB::transaction(function () use ($data, $request) {
                if (CajaApertura::where('abierta_unica', 1)->exists()) {
                    throw ValidationException::withMessages(['caja' => 'Ya existe una caja abierta. Actualiza la pantalla.']);
                }
                CajaApertura::create(['nombre' => 'Caja 01', 'monto_inicial' => $data['monto_inicial'],
                    'operador_id' => $request->user()->id, 'inicio_at' => now(), 'abierta_unica' => 1]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['caja' => 'Ya existe una caja abierta. Actualiza la pantalla.']);
        }
        return redirect()->route('admin.caja.index')->with('success', 'Caja abierta correctamente.');
    }

    public function movimiento(Request $request, CajaService $service)
    {
        $data = $request->validate([
            'tipo' => 'required|in:ingreso,egreso',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,manual',
            'monto' => 'required|numeric|gt:0|max:999999|decimal:0,2',
            'motivo' => 'required|string|min:3|max:255',
        ]);
        DB::transaction(function () use ($service, $request, $data) {
            $caja = $service->abierta(true);
            CajaMovimiento::create($data + ['caja_apertura_id' => $caja->id, 'operador_id' => $request->user()->id]);
        });
        return redirect()->route('admin.caja.index')->with('success', 'Movimiento registrado.');
    }

    public function cerrar(Request $request, CajaService $service)
    {
        $data = $request->validate([
            'efectivo_contado' => 'required|numeric|min:0|max:999999|decimal:0,2',
            'observacion' => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($data, $request, $service) {
            $caja = $service->abierta(true);
            $arqueo = $service->arqueo($caja);
            $esperado = $arqueo['efectivo_esperado'];
            $contado = LiquidacionService::centavos($data['efectivo_contado']);
            $cierre = CajaCierre::create([
                'caja_apertura_id' => $caja->id,
                'inicio_at' => $caja->inicio_at,
                'fin_at' => now(),
                'operador_id' => $request->user()->id,
                'monto_inicial' => $caja->monto_inicial,
                'efectivo_esperado' => $esperado / 100,
                'efectivo_contado' => $contado / 100,
                'diferencia' => ($contado - $esperado) / 100,
                'ingresos_manuales' => $arqueo['ingresos'] / 100,
                'egresos_manuales' => $arqueo['egresos'] / 100,
                'observacion' => $data['observacion'] ?? null,
                'totales_por_medio' => collect($arqueo['ventas'])->map(fn ($centavos) => $centavos / 100)->all(),
                'pagos_count' => $arqueo['pagos']->count(),
                'reembolsos_count' => $arqueo['reembolsos']->count(),
            ]);
            Pago::where('caja_apertura_id', $caja->id)->whereNull('caja_cierre_id')->update(['caja_cierre_id' => $cierre->id]);
            Reembolso::whereHas('pago', fn ($query) => $query->where('caja_apertura_id', $caja->id))
                ->whereNull('caja_cierre_id')->update(['caja_cierre_id' => $cierre->id]);
            $caja->update(['abierta_unica' => null]);
        });
        return redirect()->route('admin.caja.index')->with('success', 'Caja cerrada.');
    }
}
