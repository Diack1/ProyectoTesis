<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\ClienteCobro;
use App\Models\ConfiguracionPago;
use App\Models\Reserva;
use App\Models\VehiculoTipo;
use App\Models\Abono;
use App\Services\EstadiaService;
use App\Services\ImpresionService;
use App\Services\LiquidacionService;
use App\Services\ReservaService;
use App\Services\TarifaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EstadiaController extends Controller
{
    public function index(Request $request, ReservaService $reservas)
    {
        $reservas->procesarReservasAutomaticas();
        $request->validate(['buscar' => 'nullable|string|max:80', 'estado' => 'nullable|in:activas,historial,salidas', 'fecha' => 'nullable|date']);
        $query = Estadia::with('espacio', 'vehiculoTipo', 'reserva');
        if ($request->input('estado') === 'salidas') {
            $query->whereDate('hora_salida', today());
        } elseif ($request->input('estado') !== 'historial') {
            $query->activas();
        }
        if ($request->filled('fecha')) {
            $query->whereDate('hora_ingreso', $request->fecha);
        }
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(fn ($q) => $q->where('placa', 'like', '%'.$buscar.'%')->orWhere('codigo_ticket', 'like', '%'.$buscar.'%')->orWhereHas('reserva.usuario', fn ($u) => $u->where('name', 'like', '%'.$buscar.'%'))->orWhereIn('placa', \App\Models\ClienteVehiculo::select('placa')->where('activo', true)->where('nombre', 'like', '%'.$buscar.'%')));
        }

        return view('admin.estadias.index', ['estadias' => $query->latest('hora_ingreso')->paginate(15)->withQueryString(),
            'activas' => Estadia::activas()->count(), 'esperando' => Estadia::activas()->whereNull('inicio_cobro')->count(),
            'salidasHoy' => Estadia::whereDate('hora_salida', today())->count()]);
    }

    public function create(Request $request, ReservaService $service, TarifaService $tarifasService)
    {
        $service->procesarReservasAutomaticas();
        $request->validate(['buscar' => 'nullable|string|max:80', 'reserva' => 'nullable|integer', 'placa' => 'nullable|string|regex:/\A[A-Z0-9]{5,10}\z/']);
        $reserva = $request->filled('reserva') ? Reserva::with('usuario', 'espacio', 'vehiculoTipo')->findOrFail($request->reserva) : null;
        $query = Reserva::with('usuario', 'espacio')->where(fn ($q) => $q->where('estado', 'confirmada')->orWhere(fn ($q) => $q->where('pago_al_llegar', true)->where('estado', 'pendiente_pago')->where('expires_at', '>=', now())))->whereNull('inasistencia_at')->whereDoesntHave('estadia');
        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->buscarCodigo($request->buscar)->orWhereHas('usuario', fn ($u) => $u->where('name', 'like', '%'.$request->buscar.'%')->orWhere('email', 'like', '%'.$request->buscar.'%'));
            });
        }
        $espacios = Espacio::conEstadoOperativo()->with('sensor', 'vehiculoTipos')->where('activo', true)->orderBy('codigo')->get()
            ->filter(fn ($e) => $e->estado_actual === 'libre' || $e->id === $reserva?->espacio_id);

        return response()->view('admin.estadias.create', ['reserva' => $reserva, 'reservas' => $query->orderBy('fecha_reserva')->limit(20)->get(),
            'espacios' => $espacios, 'tipos' => VehiculoTipo::where('activo', true)->get(),
            'tarifasIngreso' => $tarifasService->tarifasIngreso(),
            'casilleros' => app(\App\Services\CasilleroService::class)->opciones(),
            'abonos' => \App\Models\Abono::vigentes()->with('abonado','espacio')->get()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, EstadiaService $service)
    {
        $request->merge(['placa' => strtoupper(preg_replace('/[\s-]+/', '', (string) $request->placa))]);
        $data = $request->validate(['dni'=>'nullable|regex:/^[0-9]{8}$/',
            'placa' => 'required|string|regex:/^[A-Z0-9]{5,10}$/', 'espacio_id' => 'required|integer|exists:espacios,id',
            'vehiculo_tipo_id' => 'required|integer|exists:vehiculo_tipos,id', 'tarifa_id' => 'nullable|integer|exists:tarifas,id', 'reserva_id' => 'nullable|integer|exists:reservas,id', 'abono_id'=>'nullable|integer|exists:abonos,id',
            'autoriza_movimiento'=>'sometimes|accepted','llave_recibida'=>'sometimes|accepted',
            'ubicacion_llave'=>'nullable|required_if:llave_recibida,1|string|max:100', 'salida_prevista'=>'nullable|date|after:now',
            'metodo_pago' => 'nullable|in:efectivo,yape,plin', 'pago_verificado' => 'sometimes|accepted',
            'referencia_pago' => 'nullable|string|max:80|regex:/^[A-Za-z0-9-]+$/',
            'efectivo_recibido' => 'nullable|numeric|min:0|max:999999|decimal:0,2']);
        $data['abono_id'] = null;
        if (!empty($data['dni']) && empty($data['reserva_id'])) {
            $abono = Abono::vigentes()->with('abonado','tarifa')
                ->whereHas('abonado',fn ($query)=>$query->where('dni',$data['dni']))
                ->orderByDesc('fin')->first();
            if ($abono) {
                if (!in_array($data['placa'],$abono->abonado->placas,true)) {
                    return back()->withErrors(['placa'=>'La placa no pertenece al abonado identificado.'])->withInput();
                }
                if ((int)$data['espacio_id'] !== $abono->espacio_id) {
                    return back()->withErrors(['espacio_id'=>'Selecciona el espacio asignado al abonado.'])->withInput();
                }
                if ($abono->tarifa && (int)$data['vehiculo_tipo_id'] !== $abono->tarifa->vehiculo_tipo_id) {
                    return back()->withErrors(['vehiculo_tipo_id'=>'El vehículo debe corresponder a la tarifa mensual del abonado.'])->withInput();
                }
                $data['abono_id'] = $abono->id;
                $data['tarifa_id'] = null;
            }
        }
        $estadia = $service->ingresar($data, $request->user());

        return redirect()->route('admin.espacios.operar',
            ['espacio' => $estadia->espacio_id, 'vista'=>'ticket', 'nuevo'=>1])
            ->with('success', 'Ingreso registrado. Entrega el ticket al conductor.');
    }

    public function identificar(Request $request, Estadia $estadia)
    {
        abort_unless(hash_equals($estadia->codigo_ticket, (string) $request->query('codigo')), 404);
        $url = route('admin.espacios.operar', ['espacio'=>$estadia->espacio_id,
            'vista'=>'ticket', ...($estadia->hora_salida ? ['ticket'=>$estadia->id] : [])]);
        return $request->expectsJson() ? response()->json(['url'=>$url, 'estado'=>$estadia->hora_salida ? 'finalizado' : 'activo'])
            : redirect()->to($url);
    }

    public function escanear()
    {
        return response()->view('admin.estadias.escanear')->header('Cache-Control','no-store');
    }

    public function show(Estadia $estadia, LiquidacionService $service)
    {
        $estadia->load('espacio', 'vehiculoTipo', 'reserva.usuario', 'operadorIngreso', 'operadorSalida', 'pagosSalida', 'clienteCobro');
        $liquidacion = null;
        $cotizacion = null;
        if (! $estadia->hora_salida && $estadia->inicio_cobro) {
            $corte = now();
            $liquidacion = $service->calcular($estadia, $corte);
            $cotizacion = Crypt::encryptString(json_encode(['id' => $estadia->id, 'inicio' => $estadia->inicio_cobro->toIso8601String(), 'corte' => $corte->toIso8601String()]));
        }

        return view('admin.estadias.show', compact('estadia', 'liquidacion', 'cotizacion') + ['destinos'=>Espacio::conEstadoOperativo()->where('activo',true)->orderBy('codigo')->get()->filter(fn ($e)=>$e->estado_actual === 'libre' && $e->id !== $estadia->espacio_id)]);
    }

    public function ticket(Estadia $estadia)
    {
        return view('admin.estadias.ticket', ['estadia' => $estadia->load('espacio', 'vehiculoTipo', 'reserva.usuario', 'operadorSalida', 'pagosSalida', 'clienteCobro'),
            'negocio' => ConfiguracionPago::actual(),
            'ventaActual' => \App\Models\Venta::where('origen_tipo','estadia')->where('origen_id',$estadia->id)->whereIn('estado',['vigente','corregida'])->latest('version')->first()]);
    }

    public function salida(Request $request, Estadia $estadia, EstadiaService $service, ImpresionService $impresion)
    {
        $data = $request->validate(['cotizacion' => 'required|string', 'metodo_pago' => 'nullable|in:efectivo,yape,plin',
            'referencia_pago' => 'nullable|string|max:80|regex:/^[A-Za-z0-9-]+$/', 'efectivo_recibido' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
            'pago_verificado' => 'sometimes|accepted', 'cliente_cobro_id' => 'nullable|exists:clientes_cobro,id', 'desde_mapa' => 'nullable|in:1',
            'tipo_comprobante' => 'nullable|in:boleta,factura', 'tipo_impresion' => 'nullable|in:detallado,consumo',
            'descuento_porcentaje' => 'nullable|numeric|between:0,100|decimal:0,2',
            'descuento_monto' => 'nullable|numeric|between:0,999999|decimal:0,2',
            'motivo_descuento' => 'nullable|string|max:255',
            'pagos' => 'nullable|array|max:20', 'pagos.*.metodo_pago' => 'required_with:pagos|in:efectivo,tarjeta,yape,plin,manual',
            'pagos.*.monto' => 'required_with:pagos|numeric|gt:0|max:999999|decimal:0,2',
            'pagos.*.efectivo_recibido' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
            'pagos.*.referencia_pago' => 'nullable|string|max:80|regex:/^[A-Za-z0-9-]+$/']);
        if (($data['tipo_comprobante'] ?? null) === 'factura'
            && ClienteCobro::find($data['cliente_cobro_id'] ?? 0)?->tipo_documento !== 'RUC') {
            return back()->withErrors(['cliente_cobro_id' => 'La factura requiere un cliente con RUC.'])->withInput();
        }
        $service->salir($estadia, $data, $request->user());
        $estadia->refresh();
        $resultadoImpresion = $impresion->comprobanteAutomatico($estadia);
        return redirect()->route('admin.espacios.operar',
            ['espacio'=>$estadia->espacio_id,'vista'=>'comprobante','ticket'=>$estadia->id])
            ->with('impresion_automatica',$resultadoImpresion);
    }

    public function guardarCliente(Request $request)
    {
        $data = $request->validate([
            'nombres' => 'required|string|max:100', 'apellidos' => 'required|string|max:100',
            'celular' => 'nullable|regex:/^[0-9]{9,15}$/', 'tipo_documento' => 'required|in:DNI,RUC',
            'numero_documento' => ['required', 'regex:/^[0-9]+$/', function ($attribute, $value, $fail) use ($request) {
                if (strlen($value) !== ($request->tipo_documento === 'RUC' ? 11 : 8)) $fail('El documento debe tener '.($request->tipo_documento === 'RUC' ? 11 : 8).' dígitos.');
            }],
        ]);
        $cliente = ClienteCobro::updateOrCreate(['numero_documento' => $data['numero_documento']], $data);
        return response()->json($cliente);
    }

    public function inicioManual(Request $request, Estadia $estadia, EstadiaService $service)
    {
        $data = $request->validate(['inicio_cobro' => 'required|date|before_or_equal:now', 'motivo' => 'required|string|min:10|max:1000']);
        $service->confirmarInicioManual($estadia,Carbon::parse($data['inicio_cobro']),$data['motivo'],$request->user());

        return back()->with('success','Inicio registrado con su motivo de contingencia.');
    }
}
