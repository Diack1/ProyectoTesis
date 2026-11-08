@extends('layouts.admin')
@section('title','Inicio - Parke’o')
@section('page-title','Mapa de estacionamientos')
@section('page-subtitle','Selecciona un espacio para cobrar, consultar su ticket o registrar un ingreso.')
@section('content')
<div class="map-intro"><div><span class="eyebrow">OPERACIÓN EN TIEMPO REAL</span><h2>Elige un espacio</h2><p>Los espacios ocupados abren Cobrar. Los libres abren Registrar ingreso.</p></div><a class="btn btn-secondary" href="{{ route('admin.dashboard') }}"><x-icon name="arrow"/>Actualizar mapa</a></div>
@include('partials.plano',['administrativo'=>true])
<nav class="dashboard-shortcuts" aria-label="Accesos de operación"><a href="{{ route('admin.estadias.index') }}#buscar"><x-icon name="search"/>Buscar vehículo o ticket</a><a href="{{ route('admin.reservas.index',['vista'=>'hoy']) }}"><x-icon name="clock"/>Reservas de hoy</a><a href="{{ route('admin.caja.index') }}"><x-icon name="wallet"/>Caja</a><a href="{{ route('admin.resumen.index') }}"><x-icon name="chart"/>Resumen de actividad</a></nav>
@if($llegadas->isNotEmpty())<section class="card upcoming-arrivals"><div class="panel-heading"><h2>Próximas llegadas</h2><a href="{{ route('admin.reservas.index',['vista'=>'hoy']) }}">Ver todas</a></div><div class="upcoming-list">@foreach($llegadas as $r)<a href="{{ route('admin.estadias.create',['reserva'=>$r->id]) }}"><strong>{{ $r->placa ?? 'Placa pendiente' }} · {{ $r->espacio?->codigo }}</strong><span>{{ $r->usuario?->name }}</span><small>{{ $r->fecha_reserva->format('d/m') }} · {{ substr($r->hora_inicio,0,5) }}</small></a>@endforeach</div></section>@endif
@endsection
@push('scripts') @vite('resources/js/mapa-admin.js') @endpush
