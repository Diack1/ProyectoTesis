@extends('layouts.admin')
@section('page-title','Sensores: conexión y calibración')
@section('page-subtitle','Prueba la comunicación antes de pasar un espacio de control manual a sensor.')
@section('content')
@include('admin.estadias.messages')
@if(session('sensor_token'))
<div class="alert alert-info"><strong>Credencial de {{ session('sensor_codigo') }} — copia ahora</strong><p>Se muestra solo en esta respuesta. No la compartas con clientes.</p><textarea readonly aria-label="Credencial del sensor">{{ session('sensor_token') }}</textarea></div>
@endif
<p><a class="btn btn-secondary" href="{{ route('admin.sensores.index') }}">Actualizar diagnóstico</a></p>
<div class="alert alert-info">Mide primero el espacio vacío y con vehículo. Los umbrales deben ajustarse a tu instalación. Una lectura sin eco se envía como <code>null</code>; no equivale a espacio libre.</div>
@forelse($sensores as $sensor)
<section class="admin-page-card">
<div class="panel-heading"><h2>{{ $sensor->codigo_sensor }} · {{ $sensor->espacio->codigo }}</h2><span class="badge">{{ $sensor->conexion_label }}</span></div>
<p>{{ $sensor->tipo_sensor }} · {{ $sensor->estado }} · Espacio en {{ $sensor->espacio->modo_monitoreo === 'manual' ? 'control manual (solo diagnóstico)' : 'control con sensor' }}</p>
<dl class="ticket-details">
<div><dt>Última comunicación</dt><dd>{{ $sensor->ultima_comunicacion_at?->format('d/m/Y H:i:s') ?? 'Sin datos' }}</dd></div>
<div><dt>Última distancia</dt><dd>{{ $sensor->ultima_distancia_cm !== null ? $sensor->ultima_distancia_cm.' cm' : 'Sin medición válida' }}</dd></div>
<div><dt>Estado confirmado</dt><dd>{{ $sensor->estado_estable ?? 'Por confirmar' }}</dd></div>
<div><dt>Lecturas consecutivas</dt><dd>{{ $sensor->cantidad_candidata }} / {{ $sensor->lecturas_confirmacion }}</dd></div>
</dl>
<p>Dirección de recepción: <code>{{ route('iot.lecturas',['sensor'=>$sensor->codigo_sensor]) }}</code></p>
@if(auth()->user()->tieneRol('admin','super_admin'))
<details><summary>Calibrar y configurar</summary>
<form method="post" action="{{ route('admin.sensores.update',$sensor) }}">@csrf @method('PUT')
<div class="form-grid">
@foreach(['distancia_min_cm'=>'Distancia mínima válida (cm)','umbral_ocupado_cm'=>'Ocupado hasta (cm)','umbral_libre_cm'=>'Libre desde (cm)','distancia_max_cm'=>'Distancia máxima válida (cm)','lecturas_confirmacion'=>'Lecturas para confirmar (2 a 10)','segundos_sin_senal'=>'Tiempo sin señal (15 a 300 segundos)'] as $field=>$label)
<div class="form-group"><label for="{{ $field }}-{{ $sensor->id }}">{{ $label }}</label><input id="{{ $field }}-{{ $sensor->id }}" type="number" name="{{ $field }}" step="{{ in_array($field,['lecturas_confirmacion','segundos_sin_senal'])?'1':'0.01' }}" value="{{ $sensor->$field }}" required></div>
@endforeach
<div class="form-group"><label for="estado-{{ $sensor->id }}">Recepción de lecturas</label><select id="estado-{{ $sensor->id }}" name="estado"><option value="activo" @selected($sensor->estado==='activo')>Activa</option><option value="inactivo" @selected($sensor->estado==='inactivo')>Inactiva</option></select></div>
</div><button class="btn btn-primary">Guardar calibración</button></form>
<p>La generación de credencial habilita esta integración. El espacio mantiene su modo actual; cambia a sensor desde Espacios solo después de comprobar las lecturas.</p>
<form method="post" action="{{ route('admin.sensores.token',$sensor) }}" onsubmit="return confirm('¿Generar una credencial nueva? La anterior dejará de funcionar.');">@csrf<button class="btn btn-secondary">{{ $sensor->token_hash ? 'Renovar credencial' : 'Generar credencial' }}</button></form>
</details>
@endif
</section>
@empty<div class="card">Configura primero el código y modelo del sensor desde Espacios.</div>@endforelse
@endsection
