@extends('layouts.admin')
@section('page-title','Abonados')
@section('page-subtitle','Espacio fijo mensual: techo S/ 270 · patio S/ 240.')
@section('content')
@include('admin.estadias.messages')
<div class="entry-grid"><section class="card"><h2>Clientes abonados</h2>
<form method="get" class="search-form"><input name="buscar" aria-label="Buscar por nombre o placa" placeholder="Nombre o placa" value="{{ request('buscar') }}"><button class="btn btn-secondary">Buscar</button></form>
@forelse($clientes as $cliente)<p><a href="{{ route('admin.abonados.show',$cliente) }}"><strong>{{ $cliente->nombre }}</strong> · {{ implode(', ',$cliente->placas) }}</a><br>{{ $cliente->abonos->first()?->espacio?->codigo }}</p>@empty<p>No hay abonados registrados.</p>@endforelse
{{ $clientes->links() }}</section>
<section class="card"><h2>Nuevo abonado y primera mensualidad</h2><form method="post" action="{{ route('admin.abonados.store') }}">@csrf
<div class="form-group"><label for="nombre">Nombre del cliente</label><input id="nombre" name="nombre" maxlength="150" required value="{{ old('nombre') }}"></div>
<div class="form-group"><label for="telefono">Teléfono</label><input id="telefono" name="telefono" maxlength="40" value="{{ old('telefono') }}"></div>
<div class="form-group"><label for="placas_texto">Placas autorizadas (separadas por coma, máximo 5)</label><input id="placas_texto" name="placas_texto" required value="{{ old('placas_texto') }}"><small>Un solo vehículo puede ocupar el espacio a la vez.</small></div>
@include('admin.abonados.periodo')
<button class="btn btn-primary">Registrar pago y asignar espacio</button></form></section></div>
@endsection
