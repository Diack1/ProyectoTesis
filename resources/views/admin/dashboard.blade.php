@extends('layouts.admin')
@section('title','Inicio - Parke’o')
@section('page-title','Todo listo para recibirlos')
@section('page-subtitle','Espacios, llegadas y cobros en un solo lugar.')
@section('content')
<p class="dashboard-eyebrow">TU COCHERA, EN ORDEN</p><h2 class="dashboard-section-title">¿Qué necesitas hacer?</h2>
<section class="reception-actions" aria-label="Acciones de recepción">
<a href="{{ route('admin.estadias.create') }}"><x-icon name="car"/><strong>Registrar entrada</strong><span>Vehículo que llega al estacionamiento</span></a>
<a href="{{ route('admin.estadias.index') }}#buscar"><x-icon name="ticket"/><strong>Cobrar salida</strong><span>Buscar por placa o ticket</span></a>
<a href="{{ route('admin.reservas.index',['vista'=>'hoy']) }}"><x-icon name="clock"/><strong>Ver reservas de hoy</strong><span>Llegadas reservadas pendientes</span></a>
<a href="{{ route('admin.estadias.index') }}#buscar"><x-icon name="search"/><strong>Buscar vehículo</strong><span>Consultar placa, cliente o ticket</span></a>
</section>

<div class="stats-grid-4 mb-3">
<div class="stat-card"><x-icon class="metric-icon" name="car"/><div class="stat-label">Espacios libres</div><div class="stat-value">{{ $libres }} / {{ $espacios->count() }}</div></div>
<div class="stat-card"><x-icon class="metric-icon" name="car"/><div class="stat-label">Vehículos con ticket activo</div><div class="stat-value">{{ $activas }}</div></div>
<div class="stat-card"><x-icon class="metric-icon" name="wallet"/><div class="stat-label">Pagos por verificar</div><div class="stat-value">{{ $pendientes }}</div></div>
<div class="stat-card"><x-icon class="metric-icon" name="chart"/><div class="stat-label">Cobros aprobados hoy</div><div class="stat-value">S/ {{ number_format($cobros,2) }}</div></div>
</div>
<div class="operations-grid"><div>@include('partials.plano',['administrativo'=>true])<p><a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Actualizar estados</a></p></div>
<aside class="operations-side"><div class="card"><h2>Próximas llegadas</h2>@forelse($llegadas as $r)<a class="reservation-choice" href="{{ route('admin.estadias.create',['reserva'=>$r->id]) }}"><strong>{{ $r->placa ?? 'Sin placa registrada' }} · {{ $r->espacio?->codigo }}</strong><span>{{ $r->usuario?->name }}</span><small>{{ $r->fecha_reserva->format('d/m') }} · {{ substr($r->hora_inicio,0,5) }}</small></a>@empty<p class="text-muted">No hay reservas pagadas pendientes de llegada.</p>@endforelse</div></aside></div>
@endsection
