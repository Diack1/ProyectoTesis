@extends('layouts.admin')
@section('page-title','Detalle del abonado')
@section('content')
@include('admin.estadias.messages')
@php($ultimo=$abonado->abonos->first())
<p><a href="{{ route('admin.abonados.index') }}">Volver a abonados</a></p>
<div class="entry-grid"><section class="card"><h2>{{ $abonado->nombre }}</h2><p>{{ $abonado->telefono }}</p><p>Placas autorizadas: {{ implode(', ', $abonado->placas) }}</p>
<img src="data:image/svg+xml;base64,{{ $qr }}" width="260" height="260" style="max-width:100%;height:auto;background:white" alt="QR del abonado"><p>El cliente puede mostrar este QR al encargado. Solo el personal autenticado puede consultar sus datos; escanear no registra pagos ni entradas.</p>
<h3>Mensualidades</h3>
@foreach($abonado->abonos as $a)
<article class="card"><strong>{{ $a->espacio->codigo }} · {{ ucfirst($a->zona) }} · S/ {{ number_format($a->monto,2) }}</strong><p>{{ $a->inicio->format('d/m/Y H:i') }} → {{ $a->fin->format('d/m/Y H:i') }} (fin exclusivo)</p><p>{{ now()->lt($a->inicio)?'Próximo periodo':(now()->lt($a->fin)?'Vigente':'Vencido') }} · Pago {{ $a->pago?->estado }}</p>
@if(now()->gte($a->inicio) && now()->lt($a->fin))
@php($ocupante=$a->espacio->estadias()->activas()->first())
@if($ocupante)<p>Ocupado por {{ $ocupante->placa }}.</p><a class="btn btn-secondary" href="{{ route('admin.estadias.show',$ocupante) }}">{{ $ocupante->abono_id?'Ver ticket':'Reubicar ocupante temporal' }}</a>
@else<a class="btn btn-primary" href="{{ route('admin.estadias.create',['abono_id'=>$a->id,'espacio_id'=>$a->espacio_id,'placa'=>$abonado->placas[0]]) }}">Registrar llegada</a>@endif
@endif</article>
@endforeach</section>
<section class="card"><h2>Registrar renovación pagada</h2><p>Se guarda un nuevo periodo y se conserva el historial. El servidor impide asignaciones superpuestas.</p><form method="post" action="{{ route('admin.abonados.store') }}">@csrf<input type="hidden" name="abonado_id" value="{{ $abonado->id }}">@include('admin.abonados.periodo')<button class="btn btn-primary">Registrar mensualidad</button></form></section></div>
@endsection
