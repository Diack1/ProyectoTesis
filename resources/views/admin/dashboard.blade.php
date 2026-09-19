@extends('layouts.admin')
@section('title','Inicio - Parke’o')
@section('page-title','Todo listo para recibirlos')
@section('page-subtitle','Espacios, llegadas y cobros en un solo lugar.')
@section('content')
<div class="stats-grid-4 mb-3">
<div class="stat-card"><div class="stat-label">Espacios libres</div><div class="stat-value">{{ $libres }} / {{ $espacios->count() }}</div></div>
<div class="stat-card"><div class="stat-label">Vehículos con ticket activo</div><div class="stat-value">{{ $activas }}</div></div>
<div class="stat-card"><div class="stat-label">Pagos por verificar</div><div class="stat-value">{{ $pendientes }}</div></div>
<div class="stat-card"><div class="stat-label">Cobros aprobados hoy</div><div class="stat-value">S/ {{ number_format($cobros,2) }}</div></div>
</div>
<div class="operations-grid"><div>@include('partials.plano',['administrativo'=>true])<p><a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Actualizar estados</a></p></div>
<aside class="operations-side"><div class="card"><h2>¿Qué necesitas hacer?</h2><a class="quick-card" href="{{ route('admin.estadias.create') }}"><strong>Registrar una llegada →</strong><span>Asignar espacio y emitir ticket</span></a><a class="quick-card" href="{{ route('admin.estadias.index') }}"><strong>Cobrar una salida →</strong><span>Buscar por placa o ticket</span></a><a class="quick-card" href="{{ route('admin.placas.index') }}"><strong>Reconocer una placa →</strong><span>Abrir cámara de entrada</span></a></div>
<div class="card"><h2>Próximas llegadas</h2>@forelse($llegadas as $r)<a class="reservation-choice" href="{{ route('admin.estadias.create',['reserva'=>$r->id]) }}"><strong>{{ $r->placa ?? 'Sin placa registrada' }} · {{ $r->espacio?->codigo }}</strong><span>{{ $r->usuario?->name }}</span><small>{{ $r->fecha_reserva->format('d/m') }} · {{ substr($r->hora_inicio,0,5) }}</small></a>@empty<p class="text-muted">No hay reservas pagadas pendientes de llegada.</p>@endforelse</div></aside></div>
@endsection
