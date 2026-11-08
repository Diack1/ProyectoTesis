@extends('layouts.admin')
@section('page-title','Informes · Ventas')
@section('page-subtitle','Todas las boletas y facturas, incluidas las de cajas cerradas')
@push('styles') @vite('resources/css/pages/caja.css') @endpush
@section('content')
<div class="cash-desk sale-history">
<div class="cash-heading"><h2>Ventas</h2><a class="btn btn-secondary" href="{{ route('admin.informes.index') }}">Volver a Informes</a></div>
@include('admin.informes.filtros',['ventasFiltro'=>true])
<div class="cash-heading"><strong>{{ $cantidad }} ventas vigentes · S/ {{ number_format($total,2) }}</strong><a class="btn btn-secondary" href="{{ route('admin.informes.ventas.exportar',request()->query()) }}">Exportar CSV</a></div>
<section class="card cash-section"><div class="table-responsive"><table class="data-table"><thead><tr><th>Comprobante</th><th>Fecha y hora</th><th>Cliente</th><th>Tipo</th><th>Placa / servicio</th><th>Total</th><th>Medios</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($ventas as $venta)<tr><td><strong>{{ $venta->numero }}</strong></td><td>{{ $venta->fecha?->format('d/m/Y H:i') }}</td><td>{{ $venta->cliente }}</td><td>{{ ucfirst($venta->tipo_comprobante) }}</td><td>{{ $venta->concepto }}</td><td>S/ {{ number_format($venta->total,2) }}</td><td>{{ $venta->pagos->whereIn('estado',$venta->estado==='anulada'?['cancelado']:['aprobado','reembolsado'])->pluck('metodo_pago')->unique()->map(fn($m)=>ucfirst($m==='manual'?'Otros':$m))->join(', ') ?: 'Sin pago' }}</td><td>{{ ucfirst($venta->estado) }}</td><td><button type="button" class="btn btn-secondary" data-sale-url="{{ route('admin.caja.ventas.show',$venta) }}">Ver</button></td></tr>
@empty<tr><td colspan="9">No se encontraron ventas.</td></tr>@endforelse
</tbody></table></div>{{ $ventas->links() }}</section></div>
<div class="cash-modal" id="sale-receipt-modal" hidden><div class="cash-dialog sale-receipt-dialog" role="dialog" aria-modal="true" aria-labelledby="sale-receipt-title"><button type="button" class="cash-modal-close" data-close-sale aria-label="Cerrar">×</button><div id="sale-receipt-content"></div></div></div>
@endsection
@push('scripts') @vite('resources/js/ventas.js') @endpush
