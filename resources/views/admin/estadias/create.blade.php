@extends('layouts.admin')
@section('page-title','Registrar entrada')
@section('page-subtitle','Asigna el espacio y entrega el ticket al conductor.')
@section('content')
@include('admin.estadias.messages')
<p class="operation-links"><a class="btn btn-secondary" href="{{ route('admin.placas.index') }}">Leer placa con la cámara</a> <a class="btn btn-secondary" href="{{ route('admin.estadias.index') }}"><span aria-hidden="true">←</span> Volver a entradas y salidas</a></p>
<div class="entry-grid"><section class="card"><h2>{{ $reserva?'Llegada con reserva':'Ingreso presencial' }}</h2>
@if($reserva)<div class="selected-reservation"><strong>{{ $reserva->codigo_reserva }}</strong><p>{{ $reserva->usuario->name }} · {{ $reserva->espacio->codigo }}</p><p>Adelanto: S/ {{ number_format($reserva->monto_total,2) }} · {{ $reserva->duracion_minutos }} minutos contratados</p><a href="{{ route('admin.estadias.create') }}">Cambiar a ingreso presencial</a></div>@else<p class="text-muted">El cliente paga el tiempo utilizado al retirar su vehículo.</p>@endif
<form method="post" action="{{ route('admin.estadias.store') }}">@csrf
@if($reserva)<input type="hidden" name="reserva_id" value="{{ $reserva->id }}">@endif
@if(request()->filled('placa'))<p class="alert alert-info">Placa sugerida por la consulta. Compárala con el vehículo antes de emitir el ticket.</p>@endif
<div class="form-group"><label for="placa">Placa del vehículo</label><input id="placa" name="placa" value="{{ old('placa', $reserva?->placa ?? request('placa')) }}" maxlength="11" placeholder="ABC-123" required autofocus autocomplete="off"></div>
<div id="entry-client" data-url="{{ route('admin.placas.cliente') }}" data-token="{{ csrf_token() }}" role="status" aria-live="polite"></div>
<div class="form-group"><label for="espacio_id">Espacio</label><select id="espacio_id" name="espacio_id" data-entry-space required><option value="">Selecciona un espacio</option>@foreach($espacios as $espacio)@if(!$reserva || $reserva->espacio_id===$espacio->id)<option value="{{ $espacio->id }}" data-types="{{ $espacio->vehiculoTipos->pluck('id')->implode(',') }}" data-mode="{{ $espacio->modo_monitoreo }}" @selected(old('espacio_id',$reserva?->espacio_id ?? request('espacio_id'))==$espacio->id)>{{ $espacio->codigo }} · {{ $espacio->modo_monitoreo==='sensor'?'Con sensor':'Control manual' }}</option>@endif
@endforeach</select></div>
<div class="form-group"><label for="vehiculo_tipo_id">Tipo de vehículo</label><select id="vehiculo_tipo_id" name="vehiculo_tipo_id" data-entry-type required><option value="">Selecciona el tipo</option>@foreach($tipos as $tipo)@if(!$reserva || $reserva->vehiculo_tipo_id===$tipo->id)<option value="{{ $tipo->id }}" @selected(old('vehiculo_tipo_id',$reserva?->vehiculo_tipo_id)==$tipo->id)>{{ $tipo->nombre }}</option>@endif
@endforeach</select></div>
<div class="alert alert-info" data-sensor-help>En espacios manuales, el cobro comienza al emitir el ticket. Con sensor, comienza cuando se detecta el vehículo estacionado.</div><button class="btn btn-primary"><x-icon name="ticket"/>Registrar y generar ticket</button></form></section>
<aside class="card"><span class="eyebrow">RESERVAS PAGADAS</span><h2>¿Reservó por la web?</h2><p>Busca al cliente y registra su llegada con el adelanto vinculado.</p><form method="get" class="search-form"><input name="buscar" aria-label="Buscar reserva o cliente" value="{{ request('buscar') }}" placeholder="Código, nombre o correo"><button class="btn btn-secondary">Buscar</button></form>@forelse($reservas as $r)<a class="reservation-choice" href="{{ route('admin.estadias.create',['reserva'=>$r->id]) }}"><strong>{{ $r->usuario->name }}</strong><span>{{ $r->codigo_reserva }} · {{ $r->espacio->codigo }}</span><small>{{ $r->fecha_reserva->format('d/m/Y') }} · {{ substr($r->hora_inicio,0,5) }}</small></a>@empty<div class="empty-compact">No hay reservas pagadas pendientes de llegada.</div>@endforelse</aside></div>
@endsection

@push('scripts')<script src="{{ asset('js/entrada-cliente.js') }}" defer></script>@endpush
