<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Estadia, Venta};
use App\Services\VentaService;
use Illuminate\Http\Request;

class InformeController extends Controller
{
    public function index()
    {
        return view('admin.informes.index');
    }

    private function fechas(Request $request): array
    {
        return $request->validate(['dia' => 'nullable|date_format:Y-m-d',
            'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde']);
    }

    private function filtrarFechas($query, Request $request, string $campo)
    {
        if ($request->filled('dia')) $query->whereDate($campo, $request->dia);
        if ($request->filled('desde')) $query->whereDate($campo, '>=', $request->desde);
        if ($request->filled('hasta')) $query->whereDate($campo, '<=', $request->hasta);
        return $query;
    }

    private function ventasQuery(Request $request)
    {
        $this->fechas($request);
        $request->validate(['tipo' => 'nullable|in:boleta,factura', 'estado' => 'nullable|in:vigente,anulada,corregida',
            'metodo' => 'nullable|in:efectivo,tarjeta,yape,plin,manual', 'buscar' => 'nullable|string|max:100']);
        $query = $this->filtrarFechas(Venta::query(), $request, 'fecha');
        if ($request->filled('tipo')) $query->where('tipo_comprobante', $request->tipo);
        if ($request->filled('estado')) $query->where('estado', $request->estado);
        if ($request->filled('metodo')) $query->whereHas('pagos', fn ($q) => $q->where('metodo_pago', $request->metodo));
        if ($request->filled('buscar')) {
            $text = '%'.addcslashes($request->buscar, '%_\\').'%';
            $query->where(fn ($q) => $q->where('numero', 'like', $text)->orWhere('cliente', 'like', $text)->orWhere('concepto', 'like', $text));
        }
        return $query;
    }

    public function ventas(Request $request, VentaService $service)
    {
        $service->sincronizar();
        $query = $this->ventasQuery($request);
        return view('admin.informes.ventas', [
            'ventas' => (clone $query)->with(['pagos', 'anterior'])->orderByDesc('fecha')->orderByDesc('id')->paginate(20)->withQueryString(),
            'total' => (clone $query)->where('estado', '!=', 'anulada')->sum('total'),
            'cantidad' => (clone $query)->where('estado', '!=', 'anulada')->count(),
        ]);
    }

    public function exportarVentas(Request $request, VentaService $service)
    {
        $service->sincronizar();
        $ventas = $this->ventasQuery($request)->with('pagos')->orderBy('fecha')->cursor();
        return response()->streamDownload(function () use ($ventas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Comprobante', 'Fecha', 'Cliente', 'Tipo', 'Concepto', 'Importe', 'Descuento', 'Total', 'Métodos', 'Estado'], ';');
            foreach ($ventas as $venta) fputcsv($out, [$venta->numero, $venta->fecha?->format('Y-m-d H:i:s'), $this->csvTexto($venta->cliente),
                $venta->tipo_comprobante, $this->csvTexto($venta->concepto), $venta->importe_base, $venta->descuento, $venta->total,
                $venta->pagos->pluck('metodo_pago')->unique()->join(', '), $venta->estado], ';');
            fclose($out);
        }, 'ventas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function tickets(Request $request)
    {
        $this->fechas($request);
        $query = $this->filtrarFechas(Estadia::with(['espacio', 'vehiculoTipo']), $request, 'hora_ingreso');
        return view('admin.informes.tickets', ['tickets' => $query->orderByDesc('hora_ingreso')->paginate(20)->withQueryString()]);
    }

    public function descuentos(Request $request, VentaService $service)
    {
        $this->fechas($request);
        $service->sincronizar();
        $query = $this->filtrarFechas(Venta::with(['correcciones.usuario', 'correccionOrigen.usuario']), $request, 'fecha')->where('descuento', '>', 0);
        return view('admin.informes.descuentos', ['ventas' => $query->orderByDesc('fecha')->paginate(20)->withQueryString()]);
    }

    public function anuladas(Request $request, VentaService $service)
    {
        $this->fechas($request);
        $service->sincronizar();
        $query = $this->filtrarFechas(Venta::with(['correcciones.usuario']), $request, 'fecha')->where('estado', 'anulada');
        return view('admin.informes.anuladas', ['ventas' => $query->orderByDesc('fecha')->paginate(20)->withQueryString()]);
    }

    private function csvTexto(?string $value): string
    {
        $value = (string) $value;
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
