<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Abono, Estadia, Impresora, Venta};
use App\Services\{ImpresionService, TicketQrService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImpresoraController extends Controller
{
    public function index()
    {
        return view('admin.impresoras.index', ['impresoras'=>Impresora::orderByDesc('predeterminada_unica')->orderBy('nombre')->get()]);
    }

    public function guardar(Request $request, ?Impresora $impresora = null)
    {
        $this->administrador($request);
        $data = $request->validate(['nombre'=>'required|string|min:2|max:80','tipo'=>'required|in:termica,documentos',
            'conexion'=>'required|in:sistema,usb,red,wifi,bluetooth',
            'direccion_ip'=>'nullable|ip', 'puerto'=>'nullable|integer|between:1,65535',
            'papel_mm'=>'required|in:58,80,210']);
        if ($data['tipo']==='termica' && (int)$data['papel_mm']===210)
            throw ValidationException::withMessages(['papel_mm'=>'La impresora térmica debe usar papel de 58 u 80 mm.']);
        if ($data['tipo']==='documentos' && (int)$data['papel_mm']!==210)
            throw ValidationException::withMessages(['papel_mm'=>'Selecciona A4 para una impresora de documentos.']);
        if (in_array($data['conexion'],['red','wifi'],true) && $data['tipo']==='termica'
            && !ImpresionService::ipPrivada($data['direccion_ip'] ?? null))
            throw ValidationException::withMessages(['direccion_ip'=>'Usa una IPv4 privada de la red local para impresión térmica directa.']);
        if (!in_array($data['conexion'],['red','wifi'],true) || $data['tipo']!=='termica') {
            $data['direccion_ip']=null; $data['puerto']=null;
        } else $data['puerto'] ??= 9100;
        DB::transaction(function () use ($impresora, $data) {
            if ($impresora) $impresora->update($data);
            else {
                $primera = !Impresora::query()->exists();
                Impresora::create($data + ['predeterminada_unica'=>$primera ? 1 : null]);
            }
        });
        return redirect()->route('admin.impresoras.index')->with('success','Impresora guardada.');
    }

    public function predeterminar(Request $request, Impresora $impresora)
    {
        $this->administrador($request);
        DB::transaction(function () use ($impresora) {
            Impresora::whereNotNull('predeterminada_unica')->update(['predeterminada_unica'=>null]);
            $impresora->update(['predeterminada_unica'=>1]);
        });
        return back()->with('success','Impresora predeterminada actualizada.');
    }

    public function eliminar(Request $request, Impresora $impresora)
    {
        $this->administrador($request);
        DB::transaction(function () use ($impresora) {
            $eraPrincipal = (bool)$impresora->predeterminada_unica;
            $impresora->delete();
            if ($eraPrincipal) Impresora::query()->orderBy('id')->first()?->update(['predeterminada_unica'=>1]);
        });
        return back()->with('success','Impresora eliminada.');
    }

    public function prueba(Impresora $impresora, ImpresionService $service)
    {
        if ($impresora->directa()) {
            return $this->resultadoImpresion(fn () => $service->enviar($impresora, "Parke'o\nPRUEBA DE IMPRESION\n".now()->format('d/m/Y H:i')),
                'Prueba enviada a '.$impresora->nombre);
        }
        return view('admin.impresoras.prueba', compact('impresora'));
    }

    public function estadia(Request $request, Estadia $estadia, ImpresionService $service, TicketQrService $qr)
    {
        $impresora = Impresora::predeterminada();
        if ($request->expectsJson()) {
            if (!$impresora?->directa()) return response()->json(['message'=>'Configura una impresora térmica de red o Wi-Fi para imprimir directamente sin abrir otra ventana.'], 422);
            $estadia->load('espacio','vehiculoTipo','pagosSalida');
            try {
                $service->enviar($impresora, $service->ticket($estadia),
                    $estadia->hora_salida ? null : $request->getSchemeAndHttpHost().$qr->path($estadia));
                return response()->json(['message'=>'Impresión realizada']);
            } catch (ValidationException $e) {
                return response()->json(['message'=>$e->errors()['impresora'][0] ?? 'No se pudo imprimir.'], 422);
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['message'=>'No se pudo completar la impresión. Revisa la impresora y su conexión.'], 500);
            }
        }
        if ($impresora?->directa()) {
            $estadia->load('espacio','vehiculoTipo','pagosSalida');
            return $this->resultadoImpresion(fn () => $service->enviar($impresora,$service->ticket($estadia),
                $estadia->hora_salida ? null : request()->getSchemeAndHttpHost().$qr->path($estadia)),
                'Ticket enviado a '.$impresora->nombre);
        }
        return redirect()->route('admin.estadias.ticket',[$estadia,'print'=>1,'papel'=>$impresora?->papel_mm ?: 80]);
    }

    public function abono(Abono $abono, ImpresionService $service)
    {
        $impresora = Impresora::predeterminada();
        if ($impresora?->directa()) {
            $abono->load('abonado','espacio');
            return $this->resultadoImpresion(fn () => $service->enviar($impresora,$service->abono($abono)),
                'Comprobante enviado a '.$impresora->nombre);
        }
        return redirect()->route('admin.abonados.comprobante',[$abono,'print'=>1,'papel'=>$impresora?->papel_mm ?: 80]);
    }

    public function venta(Venta $venta, ImpresionService $service)
    {
        if (auth()->user()->role !== 'super_admin') {
            $caja = \App\Models\CajaApertura::where('abierta_unica', 1)->where('operador_id', auth()->id())->first();
            abort_unless($caja && ($venta->caja_apertura_id === $caja->id || ($venta->caja_apertura_id === null
                && $venta->pagos()->where('caja_apertura_id', $caja->id)->exists())), 403);
        }
        $impresora = Impresora::predeterminada();
        if ($impresora?->directa()) {
            $venta->load('pagos');
            return $this->resultadoImpresion(fn () => $service->enviar($impresora,$service->venta($venta)),
                'Comprobante enviado a '.$impresora->nombre);
        }
        return view('admin.impresoras.venta', compact('venta','impresora'));
    }

    private function administrador(Request $request): void
    {
        abort_unless($request->user()->role === 'super_admin',403);
    }

    private function resultadoImpresion(\Closure $enviar, string $mensaje)
    {
        try { $enviar(); return view('admin.impresoras.resultado', compact('mensaje') + ['exito'=>true]); }
        catch (ValidationException $e) {
            return response()->view('admin.impresoras.resultado',
                ['mensaje'=>$e->errors()['impresora'][0] ?? 'No se pudo imprimir.', 'exito'=>false], 422);
        }
    }
}
