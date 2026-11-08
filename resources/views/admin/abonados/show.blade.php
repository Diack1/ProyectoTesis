@extends('layouts.admin')
@section('page-title','Detalle del abonado')
@section('content')
@include('admin.estadias.messages')
@php($ultimo=$abonado->abonos->first())
<p><a href="{{ route('admin.abonados.index') }}">Volver a abonados</a> <a class="btn btn-secondary" href="{{ route('admin.abonados.edit',$abonado) }}">Editar abonado</a></p>
<div class="entry-grid"><section class="card"><h2>{{ $abonado->nombre }}</h2><p>{{ $abonado->telefono }}{{ $abonado->dni ? ' · DNI '.$abonado->dni : '' }}</p><p>Placas autorizadas: {{ implode(', ', $abonado->placas) }}</p>
<img src="data:image/svg+xml;base64,{{ $qr }}" width="260" height="260" style="max-width:100%;height:auto;background:white" alt="QR del abonado"><p>El cliente puede mostrar este QR al encargado. Solo el personal autenticado puede consultar sus datos; escanear no registra pagos ni entradas.</p>
<h3>Mensualidades</h3>
@foreach($abonado->abonos as $a)
<article class="card"><strong>{{ $a->espacio->codigo }} · {{ ucfirst($a->zona) }} · S/ {{ number_format($a->monto,2) }}</strong><p>{{ $a->inicio->format('d/m/Y H:i') }} → {{ $a->fin->format('d/m/Y H:i') }} (fin exclusivo)</p><p>{{ now()->lt($a->inicio)?'Próximo periodo':(now()->lt($a->fin)?'Vigente':'Vencido') }} · {{ $a->meses }} mes(es) · {{ $a->tarifa?->nombre ?? 'Tarifa anterior' }}</p><p>Pagos: {{ $a->pagos->map(fn($p)=>ucfirst($p->metodo_pago).' S/ '.number_format($p->monto,2))->join(' · ') }}</p><form method="post" action="{{ route('admin.impresoras.abono',$a) }}" target="_blank">@csrf<button class="btn btn-secondary">Imprimir comprobante</button></form>
@if(now()->gte($a->inicio) && now()->lt($a->fin))
@php($ocupante=$a->espacio->estadias()->activas()->first())
@if($ocupante)<p>Ocupado por {{ $ocupante->placa }}.</p><a class="btn btn-secondary" href="{{ route('admin.estadias.create',['dni'=>$abonado->dni,'espacio_id'=>$a->espacio_id,'placa'=>$abonado->placas[0]]) }}">Registrar llegada / liberar espacio</a>
@else<a class="btn btn-primary" href="{{ route('admin.estadias.create',['dni'=>$abonado->dni,'espacio_id'=>$a->espacio_id,'placa'=>$abonado->placas[0]]) }}">Registrar llegada</a>@endif
@endif</article>
@endforeach
@if($abonado->ediciones()->exists())
<h3>Historial de cambios</h3>
@foreach($abonado->ediciones()->with('usuario')->get() as $edicion)
<p>{{ $edicion->created_at->format('d/m/Y H:i') }} · {{ $edicion->usuario->name }} · {{ $edicion->motivo }}<br><small>Antes: {{ json_encode($edicion->antes, JSON_UNESCAPED_UNICODE) }}<br>Después: {{ json_encode($edicion->despues, JSON_UNESCAPED_UNICODE) }}</small></p>
@endforeach
@endif
</section>
<section class="card"><h2>Renovar abono</h2><p>Selecciona el periodo y revisa el cobro. La renovación se registra solo al confirmar el pago completo.</p><a class="btn btn-primary" href="{{ route('admin.abonados.create',['abonado_id'=>$abonado->id]) }}">Registrar y pagar renovación</a></section></div>
@endsection
