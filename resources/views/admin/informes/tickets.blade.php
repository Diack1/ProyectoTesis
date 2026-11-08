@extends('layouts.admin')
@section('page-title','Informes · Tickets')
@section('page-subtitle','Entradas y salidas conservadas en el historial')
@push('styles') @vite('resources/css/pages/caja.css') @endpush
@section('content')
<div class="cash-desk"><div class="cash-heading"><h2>Tickets</h2><a class="btn btn-secondary" href="{{ route('admin.informes.index') }}">Volver a Informes</a></div>@include('admin.informes.filtros')
<section class="card cash-section"><div class="table-responsive"><table class="data-table"><thead><tr><th>Ticket</th><th>Placa</th><th>Espacio</th><th>Entrada</th><th>Salida</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($tickets as $ticket)<tr><td>{{ $ticket->codigo_ticket }}</td><td>{{ $ticket->placa }}</td><td>{{ $ticket->espacio?->codigo }}</td><td>{{ $ticket->hora_ingreso?->format('d/m/Y H:i') }}</td><td>{{ $ticket->hora_salida?->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $ticket->estado_label }}</td><td><a class="btn btn-secondary" href="{{ route('admin.estadias.ticket',$ticket) }}">Ver</a></td></tr>
@empty<tr><td colspan="7">No se encontraron tickets.</td></tr>@endforelse
</tbody></table></div>{{ $tickets->links() }}</section></div>
@endsection
