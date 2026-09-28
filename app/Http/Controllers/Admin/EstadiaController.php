<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\Reserva;
use App\Models\VehiculoTipo;
use App\Services\EstadiaService;
use App\Services\LiquidacionService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EstadiaController extends Controller
{
    public function index(Request $request, ReservaService $reservas)
    {
        $reservas->procesarReservasAutomaticas();
        $request->validate(['buscar' => 'nullable|string|max:80', 'estado' => 'nullable|in:activas,historial', 'fecha' => 'nullable|date']);
        $query = Estadia::with('espacio', 'vehiculoTipo', 'reserva');
        if ($request->input('estado') !== 'historial') {
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

    public function create(Request $request, ReservaService $service)
    {
        $service->procesarReservasAutomaticas();
        $request->validate(['buscar' => 'nullable|string|max:80', 'reserva' => 'nullable|integer', 'placa' => 'nullable|string|regex:/\A[A-Z0-9]{5,10}\z/']);
        $reserva = $request->filled('reserva') ? Reserva::with('usuario', 'espacio', 'vehiculoTipo')->findOrFail($request->reserva) : null;
        $query = Reserva::with('usuario', 'espacio')->where('estado', 'confirmada')->whereNull('inasistencia_at')->whereDoesntHave('estadia');
        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('codigo_reserva', 'like', '%'.$request->buscar.'%')->orWhereHas('usuario', fn ($u) => $u->where('name', 'like', '%'.$request->buscar.'%')->orWhere('email', 'like', '%'.$request->buscar.'%'));
            });
        }
        $espacios = Espacio::conEstadoOperativo()->with('sensor', 'vehiculoTipos')->where('activo', true)->orderBy('codigo')->get()
            ->filter(fn ($e) => $e->estado_actual === 'libre' || $e->id === $reserva?->espacio_id);

        return view('admin.estadias.create', ['reserva' => $reserva, 'reservas' => $query->orderBy('fecha_reserva')->limit(20)->get(),
            'espacios' => $espacios, 'tipos' => VehiculoTipo::where('activo', true)->get()]);
    }

    public function store(Request $request, EstadiaService $service)
    {
        $request->merge(['placa' => strtoupper(preg_replace('/[\s-]+/', '', (string) $request->placa))]);
        $data = $request->validate(['placa' => 'required|string|regex:/^[A-Z0-9]{5,10}$/', 'espacio_id' => 'required|integer|exists:espacios,id',
            'vehiculo_tipo_id' => 'required|integer|exists:vehiculo_tipos,id', 'reserva_id' => 'nullable|integer|exists:reservas,id']);
        $estadia = $service->ingresar($data, $request->user());

        return redirect()->route('admin.estadias.show', $estadia)->with('success', 'Ingreso registrado. Entrega el ticket al conductor.');
    }

    public function show(Estadia $estadia, LiquidacionService $service)
    {
        $estadia->load('espacio', 'vehiculoTipo', 'reserva.usuario', 'operadorIngreso', 'operadorSalida', 'pagoSalida');
        $liquidacion = null;
        $cotizacion = null;
        if (! $estadia->hora_salida && $estadia->inicio_cobro) {
            $corte = now();
            $liquidacion = $service->calcular($estadia, $corte);
            $cotizacion = Crypt::encryptString(json_encode(['id' => $estadia->id, 'inicio' => $estadia->inicio_cobro->toIso8601String(), 'corte' => $corte->toIso8601String()]));
        }

        return view('admin.estadias.show', compact('estadia', 'liquidacion', 'cotizacion'));
    }

    public function ticket(Estadia $estadia)
    {
        return view('admin.estadias.ticket', ['estadia' => $estadia->load('espacio', 'vehiculoTipo', 'pagoSalida')]);
    }

    public function salida(Request $request, Estadia $estadia, EstadiaService $service)
    {
        $data = $request->validate(['cotizacion' => 'required|string', 'metodo_pago' => 'nullable|in:efectivo,yape,plin',
            'referencia_pago' => 'nullable|string|max:80|regex:/^[A-Za-z0-9-]+$/', 'efectivo_recibido' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
            'pago_verificado' => 'sometimes|accepted']);
        $service->salir($estadia, $data, $request->user());

        return redirect()->route('admin.estadias.show', $estadia)->with('success', 'Cobro y salida registrados. Puedes imprimir la constancia.');
    }

    public function inicioManual(Request $request, Estadia $estadia, EstadiaService $service)
    {
        $data = $request->validate(['inicio_cobro' => 'required|date|before_or_equal:now', 'motivo' => 'required|string|min:10|max:1000']);
        $service->confirmarInicioManual($estadia,Carbon::parse($data['inicio_cobro']),$data['motivo'],$request->user());

        return back()->with('success','Inicio registrado con su motivo de contingencia.');
    }
}
