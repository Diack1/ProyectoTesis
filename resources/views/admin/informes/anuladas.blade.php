@extends('layouts.admin')
@section('page-title','Informes · Boletas anuladas')
@section('page-subtitle','Comprobantes originales conservados para auditoría')
@push('styles') @vite('resources/css/pages/caja.css') @endpush
@section('content')
<div class="cash-desk sale-history"><div class="cash-heading"><h2>Boletas anuladas</h2><a class="btn btn-secondary" href="{{ route('admin.informes.index') }}">Volver a Informes</a></div>@include('admin.informes.filtros')
<section class="card cash-section"><div class="table-responsive"><table class="data-table"><thead><tr><th>Fecha</th><th>Comprobante</th><th>Cliente</th><th>Importe</th><th>Responsable</th><th>Motivo de anulación</th><th></th></tr></thead><tbody>
@forelse($ventas as $venta)@php($correccion=$venta->correcciones->firstWhere('tipo','anular_y_corregir'))<tr><td>{{ $venta->fecha?->format('d/m/Y H:i') }}</td><td>{{ $venta->numero }}</td><td>{{ $venta->cliente }}</td><td>S/ {{ number_format($venta->total,2) }}</td><td>{{ $correccion?->usuario?->name ?? '—' }}</td><td>{{ $correccion?->motivo ?? '—' }}</td><td><button type="button" class="btn btn-secondary" data-sale-url="{{ route('admin.caja.ventas.show',$venta) }}">Ver original</button></td></tr>
@empty<tr><td colspan="7">No hay comprobantes anulados.</td></tr>@endforelse
</tbody></table></div>{{ $ventas->links() }}</section></div>
<div class="cash-modal" id="sale-receipt-modal" hidden><div class="cash-dialog sale-receipt-dialog" role="dialog" aria-modal="true" aria-labelledby="sale-receipt-title"><button type="button" class="cash-modal-close" data-close-sale aria-label="Cerrar">×</button><div id="sale-receipt-content"></div></div></div>
@endsection
@push('scripts') @vite('resources/js/ventas.js') @endpush
