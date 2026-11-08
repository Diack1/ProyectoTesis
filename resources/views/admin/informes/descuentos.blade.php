@extends('layouts.admin')
@section('page-title','Informes · Descuentos')
@section('page-subtitle','Descuentos aplicados a estacionamientos y abonados')
@push('styles') @vite('resources/css/pages/caja.css') @endpush
@section('content')
<div class="cash-desk"><div class="cash-heading"><h2>Descuentos</h2><a class="btn btn-secondary" href="{{ route('admin.informes.index') }}">Volver a Informes</a></div>@include('admin.informes.filtros')
<section class="card cash-section"><div class="table-responsive"><table class="data-table"><thead><tr><th>Fecha</th><th>Comprobante</th><th>Cliente / abonado</th><th>Importe original</th><th>Descuento</th><th>Motivo</th><th>Responsable</th></tr></thead><tbody>
@forelse($ventas as $venta)
<tr><td>{{ $venta->fecha?->format('d/m/Y H:i') }}</td><td>{{ $venta->numero }}</td><td>{{ $venta->cliente }}</td><td>S/ {{ number_format($venta->importe_base,2) }}</td><td>S/ {{ number_format($venta->descuento,2) }}</td><td>{{ $venta->datos['motivo_descuento'] ?? '—' }}</td><td>{{ $venta->correccionOrigen?->usuario?->name ?? ($venta->datos['cajero'] ?? '—') }}</td></tr>
@empty<tr><td colspan="7">No se encontraron descuentos.</td></tr>@endforelse
</tbody></table></div>{{ $ventas->links() }}</section></div>
@endsection
