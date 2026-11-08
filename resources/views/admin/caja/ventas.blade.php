@extends('layouts.admin')
@section('page-title','Historial de ventas')
@section('page-subtitle','Comprobantes de estacionamiento, reservas y abonados.')
@push('styles') @vite('resources/css/pages/caja.css') @endpush
@section('content')
@include('admin.estadias.messages')
<div class="cash-desk sale-history">
  <div class="cash-heading"><h2>Historial de ventas</h2><a class="btn btn-secondary" href="{{ route('admin.caja.index') }}">Volver a Caja</a></div>
  <form method="get" class="card sale-filters" aria-label="Filtrar ventas">
    <label>Buscar<input name="buscar" value="{{ request('buscar') }}" placeholder="Número, cliente o placa"></label>
    <label>Desde<input type="date" name="desde" value="{{ request('desde') }}"></label>
    <label>Hasta<input type="date" name="hasta" value="{{ request('hasta') }}"></label>
    <label>Comprobante<select name="tipo"><option value="">Todos</option><option value="boleta" @selected(request('tipo')==='boleta')>Boleta</option><option value="factura" @selected(request('tipo')==='factura')>Factura</option></select></label>
    <label>Estado<select name="estado"><option value="">Todos</option><option value="vigente" @selected(request('estado')==='vigente')>Vigente</option><option value="corregida" @selected(request('estado')==='corregida')>Corregida</option><option value="anulada" @selected(request('estado')==='anulada')>Anulada</option></select></label>
    <label>Medio<select name="metodo"><option value="">Todos</option>@foreach(['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','manual'=>'Otros'] as $medio=>$nombre)<option value="{{ $medio }}" @selected(request('metodo')===$medio)>{{ $nombre }}</option>@endforeach</select></label>
    <button class="btn btn-primary">Filtrar</button>
  </form>
  <section class="card cash-section"><div class="table-responsive"><table class="data-table"><thead><tr><th>Comprobante</th><th>Fecha y hora</th><th>Cliente</th><th>Tipo</th><th>Placa / servicio</th><th>Total</th><th>Medios</th><th>Estado</th><th></th></tr></thead><tbody>
  @forelse($ventas as $venta)<tr><td><strong>{{ $venta->numero }}</strong>@if($venta->venta_anterior_id)<small>Corrige {{ $venta->anterior?->numero }}</small>@endif</td><td>{{ $venta->fecha?->format('d/m/Y H:i') }}</td><td>{{ $venta->cliente }}</td><td>{{ ucfirst($venta->tipo_comprobante) }}</td><td>{{ $venta->concepto }}</td><td>S/ {{ number_format($venta->total,2) }}</td><td>{{ $venta->pagos->whereIn('estado',$venta->estado==='anulada'?['cancelado']:['aprobado','reembolsado'])->pluck('metodo_pago')->unique()->map(fn($m)=>ucfirst($m==='manual'?'Otros':$m))->join(', ') ?: 'Sin pago' }}</td><td><span class="sale-state {{ $venta->estado==='anulada' ? 'is-void' : '' }}">{{ ucfirst($venta->estado) }}</span></td><td><button type="button" class="btn btn-secondary" data-sale-url="{{ route('admin.caja.ventas.show',$venta) }}">Ver</button></td></tr>
  @empty<tr><td colspan="9">No se encontraron ventas.</td></tr>@endforelse
  </tbody></table></div><div class="sale-pagination">{{ $ventas->links() }}</div></section>
</div>
<div class="cash-modal" id="sale-receipt-modal" hidden><div class="cash-dialog sale-receipt-dialog" role="dialog" aria-modal="true" aria-labelledby="sale-receipt-title"><button type="button" class="cash-modal-close" data-close-sale aria-label="Cerrar">×</button><div id="sale-receipt-content"></div></div></div>
@endsection
@push('scripts') @vite('resources/js/ventas.js') @endpush
