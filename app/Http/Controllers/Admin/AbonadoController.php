<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Abonado, Abono, AbonadoEdicion, Espacio, Estadia, Tarifa};
use App\Services\{AbonoService, ReubicacionService};
use App\Services\LiquidacionService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use BaconQrCode\Renderer\{ImageRenderer, RendererStyle\RendererStyle, Image\SvgImageBackEnd};
use BaconQrCode\Writer;

class AbonadoController extends Controller {
    public function index(Request $request) {
        $request->validate(['buscar'=>'nullable|string|max:80', 'estado'=>'nullable|in:todos,al_dia,proximos,vencidos']);
        $buscar = trim((string) $request->input('buscar'));
        $query = Abonado::with(['abonos'=>fn ($q)=>$q->with('espacio','pagos')->orderByDesc('fin')]);
        if ($buscar !== '') $query->where(fn ($q)=>$q->where('nombre','like','%'.$buscar.'%')
            ->orWhere('telefono','like','%'.$buscar.'%')->orWhere('dni','like','%'.$buscar.'%')
            ->orWhereJsonContains('placas',strtoupper(preg_replace('/[\s-]+/','',$buscar))));
        $estado = $request->input('estado','todos');
        if ($estado !== 'todos') $query->whereHas('abonos', fn ($q)=>$q->where('fin',function($latest){
                $latest->selectRaw('MAX(fin)')->from('abonos as ultimos')->whereColumn('ultimos.abonado_id','abonados.id');
            })->where('fin', $estado==='vencidos' ? '<=' : '>', now())
            ->when($estado==='proximos',fn ($q)=>$q->where('fin','<=',now()->addDays(7)))
            ->when($estado==='al_dia',fn ($q)=>$q->where('fin','>',now()->addDays(7))));
        $clientes = $query->latest()->paginate(20)->withQueryString();
        return view('admin.abonados.index', compact('clientes','estado'));
    }
    public function create(Request $request) {
        $abonado = $request->filled('abonado_id') ? Abonado::findOrFail($request->integer('abonado_id')) : null;
        $ultimo = $abonado?->abonos()->first();
        $espacios = Espacio::conEstadoOperativo()->with('sensor','vehiculoTipos')->where('activo',true)->orderBy('codigo')->get();
        $tarifas = Tarifa::with('vehiculoTipo')->where('activo',true)->where('tipo_tarifa','mensual')->orderBy('nombre')->get();
        return view('admin.abonados.create', compact('abonado','ultimo','espacios','tarifas'));
    }
    public function edit(Abonado $abonado) {
        $periodo = $abonado->abonos()->vigentes()->with('espacio', 'tarifa')->first()
            ?? $abonado->abonos()->with('espacio', 'tarifa')->where('inicio', '>', now())
                ->whereHas('pago', fn ($query) => $query->where('estado', 'aprobado'))->first();
        return view('admin.abonados.edit', [
            'abonado' => $abonado, 'periodo' => $periodo,
            'espacios' => Espacio::conEstadoOperativo()->with('vehiculoTipos')->where('activo', true)->orderBy('codigo')->get(),
            'tarifas' => Tarifa::with('vehiculoTipo')->where('activo', true)->where('tipo_tarifa', 'mensual')->orderBy('nombre')->get(),
        ]);
    }
    public function update(Request $request, Abonado $abonado) {
        $request->merge(['placas' => collect($request->input('placas', []))
            ->map(fn ($placa) => strtoupper(preg_replace('/[\s-]+/', '', (string) $placa)))->filter()->unique()->values()->all()]);
        $data = $request->validate([
            'nombre' => 'required|string|max:150', 'telefono' => 'nullable|regex:/^[0-9]{9,15}$/',
            'dni' => 'required|regex:/^[0-9]{8}$/|unique:abonados,dni,'.$abonado->id,
            'placas' => 'required|array|min:1|max:5', 'placas.*' => 'required|regex:/^[A-Z0-9]{5,10}$/',
            'espacio_id' => 'nullable|integer|exists:espacios,id', 'tarifa_id' => 'nullable|integer|exists:tarifas,id',
            'motivo' => 'required|string|min:5|max:500', 'cambio_confirmado' => 'required|accepted',
        ]);
        DB::transaction(function () use ($abonado, $data, $request) {
            $cliente = Abonado::lockForUpdate()->findOrFail($abonado->id);
            $periodo = $cliente->abonos()->vigentes()->lockForUpdate()->first()
                ?? $cliente->abonos()->where('inicio', '>', now())
                    ->whereHas('pago', fn ($query) => $query->where('estado', 'aprobado'))->lockForUpdate()->first();
            if (!$periodo && (!empty($data['espacio_id']) || !empty($data['tarifa_id']))) {
                throw ValidationException::withMessages(['tarifa_id' => 'Para cambiar el plan o espacio de un periodo vencido, registra una renovación.']);
            }
            $antes = ['nombre'=>$cliente->nombre, 'telefono'=>$cliente->telefono, 'dni'=>$cliente->dni,
                'placas'=>$cliente->placas, 'espacio_id'=>$periodo?->espacio_id, 'tarifa_id'=>$periodo?->tarifa_id];
            $nuevoEspacio = $periodo ? (int) ($data['espacio_id'] ?? $periodo->espacio_id) : null;
            $nuevaTarifa = $periodo ? (int) ($data['tarifa_id'] ?? $periodo->tarifa_id) : null;
            if ($periodo && ($nuevoEspacio !== $periodo->espacio_id || $nuevaTarifa !== $periodo->tarifa_id)) {
                $spaces = Espacio::whereIn('id', [$periodo->espacio_id, $nuevoEspacio])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $espacio = $spaces->get($nuevoEspacio);
                $tarifa = Tarifa::where('activo', true)->where('tipo_tarifa', 'mensual')->find($nuevaTarifa);
                if (!$espacio || !$espacio->activo || !$tarifa
                    || !$espacio->vehiculoTipos()->where('vehiculo_tipos.id', $tarifa->vehiculo_tipo_id)->exists()) {
                    throw ValidationException::withMessages(['espacio_id' => 'El espacio y el plan mensual deben ser activos y compatibles.']);
                }
                if ($nuevaTarifa !== $periodo->tarifa_id
                    && LiquidacionService::centavos($tarifa->monto_base) * $periodo->meses
                        !== LiquidacionService::centavos($periodo->monto_base ?? $periodo->monto)) {
                    throw ValidationException::withMessages(['tarifa_id' => 'Este plan tiene otro precio. Registra una renovación y su cobro; no se puede alterar una mensualidad ya pagada.']);
                }
                if ($nuevoEspacio !== $periodo->espacio_id) {
                    if (Estadia::activas()->whereIn('abono_id', $cliente->abonos()->select('id'))->exists()) {
                        throw ValidationException::withMessages(['espacio_id' => 'Registra primero la salida del vehículo abonado antes de cambiar su espacio habitual.']);
                    }
                    if (Abono::where('id', '!=', $periodo->id)->where('espacio_id', $nuevoEspacio)
                        ->where('inicio', '<', $periodo->fin)->where('fin', '>', $periodo->inicio)->exists()) {
                        throw ValidationException::withMessages(['espacio_id' => 'Otro abono tiene asignado ese espacio durante el periodo vigente.']);
                    }
                    if ($espacio->estado_actual !== 'libre' && !$espacio->estadias()->activas()->exists()) {
                        throw ValidationException::withMessages(['espacio_id' => 'El estado físico del nuevo espacio no permite asignarlo.']);
                    }
                    if ($espacio->reservas()->whereIn('estado', ['pendiente_pago','confirmada'])
                        ->whereNull('inasistencia_at')->whereDoesntHave('estadia')->exists()) {
                        throw ValidationException::withMessages(['espacio_id' => 'El nuevo espacio tiene una reserva pendiente.']);
                    }
                }
                if ($nuevaTarifa !== $periodo->tarifa_id && Estadia::activas()
                    ->whereIn('abono_id', $cliente->abonos()->select('id'))
                    ->where('vehiculo_tipo_id', '!=', $tarifa->vehiculo_tipo_id)->exists()) {
                    throw ValidationException::withMessages(['tarifa_id' => 'El plan no corresponde al vehículo abonado que está dentro.']);
                }
                $periodo->update(['espacio_contratado_codigo'=>$periodo->espacio_contratado_codigo ?: $spaces->get($periodo->espacio_id)?->codigo,
                    'tarifa_contratada_nombre'=>$periodo->tarifa_contratada_nombre ?: $periodo->tarifa?->nombre,
                    'espacio_id'=>$nuevoEspacio, 'tarifa_id'=>$nuevaTarifa,
                    'zona'=>\App\Services\PlanoEspacios::numero($espacio->codigo) <= 26 ? 'techo' : 'patio']);
            }
            if (Estadia::activas()->whereIn('abono_id', $cliente->abonos()->select('id'))
                ->whereNotIn('placa', $data['placas'])->exists()) {
                throw ValidationException::withMessages(['placas' => 'No retires una placa mientras tenga un ingreso activo.']);
            }
            $cliente->update(collect($data)->only(['nombre', 'telefono', 'dni', 'placas'])->all());
            $despues = ['nombre'=>$cliente->nombre, 'telefono'=>$cliente->telefono, 'dni'=>$cliente->dni,
                'placas'=>$cliente->placas, 'espacio_id'=>$periodo?->fresh()->espacio_id, 'tarifa_id'=>$periodo?->fresh()->tarifa_id];
            if ($antes !== $despues) AbonadoEdicion::create(['abonado_id'=>$cliente->id, 'abono_id'=>$periodo?->id,
                'usuario_id'=>$request->user()->id, 'antes'=>$antes, 'despues'=>$despues, 'motivo'=>$data['motivo']]);
        });
        return redirect()->route('admin.abonados.show', $abonado)->with('success', 'Datos del abonado actualizados. Los pagos y vigencias no cambiaron.');
    }
    public function preparar(Request $request) {
        if ($request->has('placas')) {
            $placas = collect($request->input('placas',[]))->filter()->map(fn ($p)=>strtoupper(preg_replace('/[\s-]+/','',(string)$p)))->unique()->values()->all();
            $request->merge(['placas'=>$placas]);
        }
        $data = $request->validate(['abonado_id'=>'nullable|integer|exists:abonados,id',
            'nombre'=>'required_without:abonado_id|nullable|string|max:150', 'telefono'=>'required_without:abonado_id|nullable|regex:/^[0-9]{9,15}$/',
            'dni'=>'required_without:abonado_id|nullable|regex:/^[0-9]{8}$/',
            'placas'=>'required_without:abonado_id|array|min:1|max:5', 'placas.*'=>'regex:/^[A-Z0-9]{5,10}$/',
            'espacio_id'=>'required|integer|exists:espacios,id', 'tarifa_id'=>'required|integer|exists:tarifas,id',
            'meses'=>'required|integer|in:1,2,3,4', 'inicio'=>'required|date_format:Y-m-d'],
            ['meses.required'=>'Selecciona la cantidad de meses.',
                'meses.integer'=>'Selecciona una cantidad de meses válida (1, 2, 3 o 4).',
                'meses.in'=>'Selecciona una cantidad de meses válida (1, 2, 3 o 4).']);
        $data['meses'] = (int) $data['meses'];
        $abonado = isset($data['abonado_id']) ? Abonado::findOrFail($data['abonado_id']) : null;
        if ($abonado) {
            $data['nombre']=$abonado->nombre; $data['telefono']=$abonado->telefono ?: ($data['telefono'] ?? null);
            $data['dni']=$abonado->dni ?: ($data['dni'] ?? null); $data['placas']=$abonado->placas;
        }
        if (empty($data['dni']) || !preg_match('/^[0-9]{8}$/',$data['dni'])) {
            throw ValidationException::withMessages(['dni'=>'Ingresa un DNI de exactamente 8 dígitos.']);
        }
        if (Abonado::where('dni',$data['dni'])->when($abonado,fn($q)=>$q->where('id','!=',$abonado->id))->exists()) {
            throw ValidationException::withMessages(['dni'=>'Este DNI ya pertenece a otro abonado.']);
        }
        $tarifa = Tarifa::where('activo',true)->where('tipo_tarifa','mensual')->findOrFail($data['tarifa_id']);
        if ((float)$tarifa->monto_base<=0) throw ValidationException::withMessages(['tarifa_id'=>'Selecciona una tarifa mensual con precio válido.']);
        $espacio = Espacio::conEstadoOperativo()->with('sensor')->findOrFail($data['espacio_id']);
        $inicio=Carbon::parse($data['inicio'])->startOfDay(); $fin=$inicio->copy()->addMonthsNoOverflow($data['meses']);
        $ocupante=$espacio->estadias()->activas()->first();
        $ocupacionPropia=$ocupante && $abonado && $ocupante->abono?->abonado_id===$abonado->id;
        if (!$espacio->activo || !$espacio->vehiculoTipos()->where('vehiculo_tipos.id',$tarifa->vehiculo_tipo_id)->exists()
            || (!$ocupante && $espacio->estado_actual!=='libre')
            || $espacio->reservas()->whereIn('estado',['pendiente_pago','confirmada'])->whereNull('inasistencia_at')->whereDoesntHave('estadia')->exists()
            || Abono::where('espacio_id',$espacio->id)->where('inicio','<',$fin)->where('fin','>',$inicio)->exists()) {
            throw ValidationException::withMessages(['espacio_id'=>'El espacio no está disponible para esta tarifa y periodo.']);
        }
        if ($fin->lte(now())) throw ValidationException::withMessages(['inicio'=>'El periodo debe ser actual o futuro.']);
        $data['monto_base_centavos']=LiquidacionService::centavos($tarifa->monto_base)*$data['meses'];
        $data['fin']=$fin->toDateString(); $data['token']=(string)Str::uuid();
        $request->session()->put('abono_cobro_draft',$data);
        return redirect()->route('admin.abonados.cobro');
    }
    public function cobro(Request $request) {
        $abonoDraft=$request->session()->get('abono_cobro_draft');
        if (!$abonoDraft) return redirect()->route('admin.abonados.create');
        $tarifaAbono=Tarifa::findOrFail($abonoDraft['tarifa_id']);
        if (!$tarifaAbono->activo || $tarifaAbono->tipo_tarifa!=='mensual'
            || LiquidacionService::centavos($tarifaAbono->monto_base)*$abonoDraft['meses']!==$abonoDraft['monto_base_centavos']) {
            $request->session()->forget('abono_cobro_draft');
            return redirect()->route('admin.abonados.create')->withErrors(['tarifa_id'=>'La tarifa cambió. Revisa el importe antes de cobrar.'])->withInput($abonoDraft);
        }
        $espacioAbono=Espacio::findOrFail($abonoDraft['espacio_id']);
        $partes=explode(' ',trim($abonoDraft['nombre']),2);
        $clienteInicial=['nombres'=>$partes[0],'apellidos'=>$partes[1]??$partes[0],
            'tipo_documento'=>'DNI','numero_documento'=>$abonoDraft['dni'],'celular'=>$abonoDraft['telefono']];
        return response()->view('admin.abonados.cobro',compact('abonoDraft','tarifaAbono','espacioAbono','clienteInicial'))
            ->header('Cache-Control','no-store');
    }
    public function cancelarCobro(Request $request) {
        $request->session()->forget('abono_cobro_draft');
        return redirect()->route('admin.abonados.create')->with('success','Cobro cancelado. No se asignó ningún espacio.');
    }
    public function buscarDni(Request $request) {
        $data = $request->validate(['dni'=>'required|regex:/^[0-9]{8}$/']);
        $abono = Abono::vigentes()->with('abonado','espacio.vehiculoTipos','tarifa')
            ->whereHas('abonado',fn ($query)=>$query->where('dni',$data['dni']))
            ->orderByDesc('fin')->first();
        if (!$abono) return response()->json(['vigente'=>false,
            'mensaje'=>'No se encontró un abono vigente para este DNI. Puedes continuar con el ingreso normal.']);
        $ocupante = $abono->espacio->estadias()->activas()->first();
        return response()->json(['vigente'=>true, 'abono_id'=>$abono->id,
            'cliente'=>$abono->abonado->nombre, 'placas'=>$abono->abonado->placas,
            'placas_activas'=>Estadia::activas()->whereIn('placa',$abono->abonado->placas)->pluck('placa'),
            'espacio_id'=>$abono->espacio_id, 'espacio_codigo'=>$abono->espacio->codigo,
            'espacio_tipos'=>$abono->espacio->vehiculoTipos->where('activo',true)->pluck('id')->values(),
            'espacio_modo'=>$abono->espacio->modo_monitoreo,
            'espacio_estado'=>$abono->espacio->estado_actual,
            'tarifa_mensual'=>$abono->tarifa
                ? $abono->tarifa->nombre.' · S/ '.number_format((float)$abono->monto_base/max(1,$abono->meses),2).' / mes'
                : 'Mensualidad vigente',
            'vence'=>$abono->fin->format('d/m/Y'),
            'vehiculo_tipo_id'=>$abono->tarifa?->vehiculo_tipo_id
                ?? $abono->espacio->vehiculoTipos->firstWhere('activo',true)?->id,
            'ocupante_placa'=>$ocupante?->placa,
            'mover_url'=>$ocupante && !$ocupante->abono_id
                ? route('admin.espacios.operar',['espacio'=>$abono->espacio_id,'vista'=>'mover','retorno_ingreso'=>1]) : null]);
    }
    public function store(Request $request, AbonoService $service, \App\Services\ImpresionService $impresion) {
        $draft=$request->session()->get('abono_cobro_draft');
        if ($draft && $request->input('draft_token')===$draft['token']) {
            $cobro=$request->validate(['draft_token'=>'required|string', 'cliente_cobro_id'=>'nullable|exists:clientes_cobro,id',
                'tipo_comprobante'=>'required|in:boleta,factura', 'tipo_impresion'=>'required|in:detallado,consumo',
                'descuento_porcentaje'=>'nullable|numeric|between:0,100|decimal:0,2',
                'descuento_monto'=>'nullable|numeric|between:0,999999|decimal:0,2', 'motivo_descuento'=>'nullable|string|max:255',
                'pago_verificado'=>'sometimes|accepted', 'pagos'=>'nullable|array|max:20',
                'pagos.*.metodo_pago'=>'required|in:efectivo,tarjeta,yape,plin,manual',
                'pagos.*.monto'=>'required|numeric|gt:0|max:999999|decimal:0,2',
                'pagos.*.efectivo_recibido'=>'nullable|numeric|min:0|max:999999|decimal:0,2']);
            $abono=$service->registrar(array_merge($draft,$cobro),$request->user());
            $request->session()->forget('abono_cobro_draft');
            $resultadoImpresion=$impresion->comprobanteAutomatico($abono);
            return redirect()->route('admin.abonados.pagado',$abono)->with('impresion_automatica',$resultadoImpresion);
        }
        if ($request->filled('draft_token')) throw ValidationException::withMessages(['pago'=>'El cobro ya no está disponible. Vuelve a registrar el abono.']);
        $placas = array_values(array_unique(array_filter(array_map(fn ($p)=>strtoupper(preg_replace('/[\s-]+/','',$p)), explode(',', (string)$request->input('placas_texto'))))));
        $request->merge(['placas'=>$placas]);
        $data = $request->validate(['abonado_id'=>'nullable|exists:abonados,id','nombre'=>'required_without:abonado_id|nullable|string|max:150',
            'telefono'=>'nullable|string|max:40','placas'=>'required_without:abonado_id|array|max:5','placas.*'=>'regex:/\A[A-Z0-9]{5,10}\z/',
            'espacio_id'=>'required|exists:espacios,id','inicio'=>'required|date_format:Y-m-d','pago_verificado'=>'required|accepted',
            'metodo_pago'=>'required|in:efectivo,yape,plin','efectivo_recibido'=>'nullable|numeric|min:0|max:999999|decimal:0,2',
            'referencia_pago'=>'nullable|string|max:80']);
        try { $abono = $service->registrar($data, $request->user()); }
        catch (UniqueConstraintViolationException $e) { throw ValidationException::withMessages(['pago'=>'La operación ya está registrada. Revisa antes de volver a cobrar.']); }
        return redirect()->route('admin.abonados.show',$abono->abonado_id)->with('success','Mensualidad pagada y espacio asignado.');
    }
    public function show(Abonado $abonado) {
        $abonado->load(['abonos'=>fn ($q)=>$q->with('espacio','pagos','tarifa','clienteCobro')->orderByDesc('inicio')]);
        $qr = base64_encode((new Writer(new ImageRenderer(new RendererStyle(300,4),new SvgImageBackEnd())))->writeString(URL::signedRoute('admin.abonados.qr',$abonado)));
        return response()->view('admin.abonados.show',['abonado'=>$abonado,'qr'=>$qr,'espacios'=>Espacio::where('activo',true)->orderBy('codigo')->get()])->header('Cache-Control','no-store');
    }
    public function qr(Abonado $abonado) { return redirect()->route('admin.abonados.show',$abonado); }
    public function comprobante(Abono $abono) {
        $abono->load('abonado','espacio','tarifa','clienteCobro','pagos');
        $negocio=\App\Models\ConfiguracionPago::actual();
        $ventaActual=\App\Models\Venta::where('origen_tipo','abono')->where('origen_id',$abono->id)->whereIn('estado',['vigente','corregida'])->latest('version')->first();
        return response()->view('admin.abonados.comprobante',compact('abono','negocio','ventaActual'))->header('Cache-Control','no-store');
    }
    public function pagado(Abono $abono) {
        $ventaComprobante=\App\Models\Venta::with('pagos')->where('origen_tipo','abono')->where('origen_id',$abono->id)
            ->whereIn('estado',['vigente','corregida'])->latest('version')->firstOrFail();
        return response()->view('admin.abonados.pagado',compact('abono','ventaComprobante'))->header('Cache-Control','no-store');
    }
    public function mover(Request $request, Estadia $estadia, ReubicacionService $service) {
        $data = $request->validate(['origen_id'=>'required|integer|exists:espacios,id','destino_id'=>'required|integer|exists:espacios,id',
            'motivo'=>'required|string|min:5|max:500','movimiento_confirmado'=>'required|accepted',
            'autoriza_movimiento_confirmada'=>'sometimes|accepted',
            'desde_mapa'=>'nullable|in:1', 'retorno_ingreso'=>'nullable|in:1']);
        $service->mover($estadia,$data,$request->user());
        if (!empty($data['retorno_ingreso'])) return redirect()->route('admin.estadias.create',['reubicado'=>1])
            ->with('success','Espacio liberado. Revisa los datos conservados y registra el ingreso del abonado.');
        return redirect()->route(!empty($data['desde_mapa']) ? 'admin.espacios.operar' : 'admin.estadias.show',
            !empty($data['desde_mapa']) ? ['espacio'=>$data['destino_id'],'vista'=>'historial'] : $estadia)
            ->with('success','Reubicación registrada. Se conservan el ticket, contador y tarifa.');
    }
}
