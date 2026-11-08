@extends('layouts.admin')
@section('page-title','Editar abonado')
@section('page-subtitle','Actualiza los datos y la asignación operativa sin modificar pagos anteriores.')
@push('styles') @vite('resources/css/pages/abonados.css') @endpush
@section('content')
@include('admin.estadias.messages')
<p><a class="btn btn-secondary" href="{{ route('admin.abonados.show',$abonado) }}">← Volver al abonado</a></p>
<section class="card subscriber-form-card">
<form method="post" action="{{ route('admin.abonados.update',$abonado) }}" class="subscriber-edit-form">@csrf @method('PUT')
<div class="subscriber-form-grid">
<div class="form-group"><label for="subscriber-name">Nombre del cliente</label><input id="subscriber-name" name="nombre" maxlength="150" required value="{{ old('nombre',$abonado->nombre) }}"></div>
<div class="form-group"><label for="subscriber-dni">DNI</label><input id="subscriber-dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="{{ old('dni',$abonado->dni) }}"></div>
<div class="form-group"><label for="subscriber-phone">Teléfono</label><input id="subscriber-phone" name="telefono" inputmode="numeric" pattern="[0-9]{9,15}" maxlength="15" value="{{ old('telefono',$abonado->telefono) }}"></div>
</div>
<div class="subscriber-field-heading"><strong>Placas autorizadas</strong><span>Hasta 5 placas; conserva las que tengan un ingreso activo.</span></div>
<div class="subscriber-form-grid">@foreach(range(0,4) as $i)<div class="form-group"><label for="plate-{{ $i }}">Placa {{ $i+1 }}</label><input id="plate-{{ $i }}" name="placas[]" maxlength="11" pattern="[A-Za-z0-9-]{5,11}" value="{{ old('placas.'.$i,$abonado->placas[$i] ?? '') }}"></div>@endforeach</div>
@if($periodo)
<div class="subscriber-form-grid subscriber-contract">
<div class="form-group"><label for="edit-space">Espacio habitual</label><select id="edit-space" name="espacio_id" required>@foreach($espacios as $espacio)<option value="{{ $espacio->id }}" @selected(old('espacio_id',$periodo->espacio_id)==$espacio->id)>{{ $espacio->codigo }} · {{ $espacio->estado_actual==='libre' ? 'libre' : $espacio->estado_actual }}</option>@endforeach</select></div>
<div class="form-group"><label for="edit-rate">Plan mensual operativo</label><select id="edit-rate" name="tarifa_id" required>@foreach($tarifas as $tarifa)<option value="{{ $tarifa->id }}" @selected(old('tarifa_id',$periodo->tarifa_id)==$tarifa->id)>{{ $tarifa->nombre }} · {{ $tarifa->vehiculoTipo?->nombre }} · S/ {{ number_format($tarifa->monto_base,2) }}/mes</option>@endforeach</select></div>
</div>
<p class="text-muted">El periodo pagado permanece del {{ $periodo->inicio->format('d/m/Y') }} al {{ $periodo->fin->format('d/m/Y') }}. El plan y el espacio del comprobante original, el importe y los pagos históricos se conservan.</p>
@else<p class="text-muted">No hay una mensualidad vigente. Puedes editar los datos personales y placas; registra una renovación para cambiar el plan o espacio.</p>@endif
<div class="form-group"><label for="edit-motive">Motivo del cambio</label><input id="edit-motive" name="motivo" minlength="5" maxlength="500" required value="{{ old('motivo') }}"></div>
<label class="entry-key-check"><input type="checkbox" name="cambio_confirmado" value="1" required> Revisé los cambios y confirmo que los pagos anteriores no se modificarán.</label>
<div class="entry-submit-actions"><button class="btn btn-primary">Guardar cambios</button></div>
</form></section>
@endsection
