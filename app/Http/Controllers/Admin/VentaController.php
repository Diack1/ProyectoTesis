<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CajaApertura, ClienteCobro, ConfiguracionPago, Venta};
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(Request $request, VentaService $service)
    {
        $data = $request->validate(['buscar'=>'nullable|string|max:100', 'desde'=>'nullable|date_format:Y-m-d',
            'hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde', 'tipo'=>'nullable|in:boleta,factura',
            'estado'=>'nullable|in:vigente,anulada,corregida', 'metodo'=>'nullable|in:efectivo,tarjeta,yape,plin,manual']);
        $service->sincronizar();
        $query = Venta::with(['pagos', 'anterior'])->orderByDesc('fecha')->orderByDesc('id');
        $caja = CajaApertura::where('abierta_unica', 1)->first();
        if ($request->user()->role !== 'super_admin' && $caja?->operador_id !== $request->user()->id) $caja = null;
        if ($caja) {
            $query->where(fn ($q) => $q->where('caja_apertura_id', $caja->id)
                ->orWhere(fn ($q) => $q->whereNull('caja_apertura_id')->whereHas('pagos', fn ($p) => $p->where('caja_apertura_id', $caja->id))));
        } else $query->whereRaw('1 = 0');
        if ($request->filled('buscar')) {
            $buscar = '%'.addcslashes($data['buscar'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('numero', 'like', $buscar)->orWhere('cliente', 'like', $buscar)->orWhere('concepto', 'like', $buscar));
        }
        if ($request->filled('desde')) $query->whereDate('fecha', '>=', $data['desde']);
        if ($request->filled('hasta')) $query->whereDate('fecha', '<=', $data['hasta']);
        if ($request->filled('tipo')) $query->where('tipo_comprobante', $data['tipo']);
        if ($request->filled('estado')) $query->where('estado', $data['estado']);
        if ($request->filled('metodo')) $query->whereHas('pagos', fn ($q) => $q->where('metodo_pago', $data['metodo']));
        return view('admin.caja.ventas', ['ventas'=>$query->paginate(20)->withQueryString()]);
    }

    public function show(Venta $venta)
    {
        $this->autorizarLectura($venta);
        $venta->load(['pagos', 'correcciones.usuario', 'correcciones.ventaNueva', 'anterior']);
        return view('admin.caja.venta-comprobante', ['venta'=>$venta, 'negocio'=>ConfiguracionPago::actual(),
            'puedeCorregir'=>auth()->user()->role === 'super_admin']);
    }

    public function edit(Request $request, Venta $venta)
    {
        $this->autorizar($request);
        if (!in_array($venta->estado, ['vigente','corregida'], true)) return redirect()->route('admin.caja.ventas.index')->withErrors(['venta'=>'El comprobante fue anulado.']);
        $tipo = $request->query('tipo') === 'pagos' ? 'pagos' : 'venta';
        $venta->load(['pagos' => fn ($q) => $q->whereIn('estado', ['aprobado','reembolsado'])]);
        $documento = preg_replace('/\D/', '', (string) $venta->documento);
        $cliente = old('cliente_cobro_id') ? ClienteCobro::find(old('cliente_cobro_id')) : null;
        $cliente ??= $documento ? ClienteCobro::where('numero_documento', $documento)->first() : null;
        $clienteInicial = $cliente?->only(['id','nombres','apellidos','tipo_documento','numero_documento','celular'])
            ?? ['id'=>null, 'nombres'=>$venta->cliente, 'apellidos'=>'',
                'tipo_documento'=>str_starts_with((string) $venta->documento, 'RUC') ? 'RUC' : 'DNI',
                'numero_documento'=>$documento, 'celular'=>null];
        return view('admin.caja.venta-cobro', ['ventaCorreccion'=>$venta, 'tipo'=>$tipo,
            'clienteInicial'=>$clienteInicial, 'idempotencia'=>(string) Str::uuid()]);
    }

    public function update(Request $request, Venta $venta, VentaService $service)
    {
        $this->autorizar($request);
        $soloPagos = $request->input('tipo') === 'pagos';
        $rules = ['tipo'=>'required|in:pagos,venta', 'motivo'=>'required|string|min:5|max:500',
            'idempotencia'=>'required|uuid', 'pagos'=>'nullable|array', 'pagos.*.metodo_pago'=>'required|in:efectivo,tarjeta,yape,plin,manual',
            'pagos.*.monto'=>'required|numeric|gt:0|decimal:0,2',
            'pagos.*.efectivo_recibido'=>'nullable|numeric|min:0|decimal:0,2'];
        if (!$soloPagos) $rules += ['importe_base'=>'required|numeric|min:0|max:999999|decimal:0,2',
            'total'=>'required|numeric|min:0|max:999999|decimal:0,2',
            'descuento_porcentaje'=>'required|numeric|min:0|max:100|decimal:0,2',
            'descuento_monto'=>'required|numeric|min:0|max:999999|decimal:0,2',
            'tipo_comprobante'=>'required|in:boleta,factura', 'tipo_impresion'=>'required|in:detallado,consumo',
            'cliente_cobro_id'=>'nullable|integer|exists:clientes_cobro,id', 'tarifa'=>'nullable|string|max:100',
            'motivo_descuento'=>'nullable|string|max:255'];
        $data = $request->validate($rules);
        if (!$soloPagos && $data['tipo_comprobante'] === 'factura') {
            $cliente = !empty($data['cliente_cobro_id']) ? ClienteCobro::find($data['cliente_cobro_id']) : null;
            if (($cliente && $cliente->tipo_documento !== 'RUC') || (!$cliente && !str_starts_with((string) $venta->documento,'RUC '))) {
                throw ValidationException::withMessages(['cliente_cobro_id'=>'La factura requiere un cliente con RUC.']);
            }
        }
        $nueva = $service->corregir($venta, $data, $request->user(), $soloPagos);
        return redirect()->route('admin.caja.ventas.index')->with('success',
            $soloPagos ? 'Pagos corregidos. Se conservó el comprobante.' : 'Comprobante anterior anulado y nueva versión registrada.');
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->role === 'super_admin', 403);
    }

    private function autorizarLectura(Venta $venta): void
    {
        if (auth()->user()->role === 'super_admin') return;
        $caja = CajaApertura::where('abierta_unica', 1)->where('operador_id', auth()->id())->first();
        abort_unless($caja && ($venta->caja_apertura_id === $caja->id || ($venta->caja_apertura_id === null
            && $venta->pagos()->where('caja_apertura_id', $caja->id)->exists())), 403);
    }
}
