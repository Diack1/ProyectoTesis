<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Abonado, Abono, Espacio, Estadia};
use App\Services\{AbonoService, ReubicacionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use BaconQrCode\Renderer\{ImageRenderer, RendererStyle\RendererStyle, Image\SvgImageBackEnd};
use BaconQrCode\Writer;

class AbonadoController extends Controller {
    public function index(Request $request) {
        $request->validate(['buscar'=>'nullable|string|max:80']);
        $clientes = Abonado::with('abonos.espacio')->when($request->filled('buscar'), fn ($q)=>$q->where(fn ($q)=>$q->where('nombre','like','%'.$request->buscar.'%')->orWhereJsonContains('placas',strtoupper(preg_replace('/[\s-]+/','',$request->buscar)))))->latest()->paginate(20)->withQueryString();
        return view('admin.abonados.index', ['clientes'=>$clientes,'espacios'=>Espacio::where('activo',true)->orderBy('codigo')->get()]);
    }
    public function store(Request $request, AbonoService $service) {
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
        $abonado->load(['abonos'=>fn ($q)=>$q->with('espacio','pago')->orderByDesc('inicio')]);
        $qr = base64_encode((new Writer(new ImageRenderer(new RendererStyle(300,4),new SvgImageBackEnd())))->writeString(URL::signedRoute('admin.abonados.qr',$abonado)));
        return response()->view('admin.abonados.show',['abonado'=>$abonado,'qr'=>$qr,'espacios'=>Espacio::where('activo',true)->orderBy('codigo')->get()])->header('Cache-Control','no-store');
    }
    public function qr(Abonado $abonado) { return redirect()->route('admin.abonados.show',$abonado); }
    public function mover(Request $request, Estadia $estadia, ReubicacionService $service) {
        $data = $request->validate(['origen_id'=>'required|integer|exists:espacios,id','destino_id'=>'required|integer|exists:espacios,id',
            'motivo'=>'required|string|min:5|max:500','movimiento_confirmado'=>'required|accepted']);
        $service->mover($estadia,$data,$request->user());
        return redirect()->route('admin.estadias.show',$estadia)->with('success','Reubicación registrada. Se conservan el ticket, contador y tarifa.');
    }
}
