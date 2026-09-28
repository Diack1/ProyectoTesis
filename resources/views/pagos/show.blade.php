@extends('layouts.public')
@section('title', 'Pago de reserva - Parke’o')
@section('content')
<section class="page-content"><div class="container" style="max-width:850px"><div class="page-card">
@include('partials.booking-steps',['step'=>3])
<span class="eyebrow">RESERVA WEB · PAGO ANTICIPADO</span><h1>Pago de tu reserva</h1>
@include('partials.payment-feedback')
<p><strong>{{ $reserva->codigo_reserva }}</strong> <span>· Placa: {{ $reserva->placa ?? 'Sin placa registrada' }}</span> · Espacio {{ $reserva->espacio->codigo }} · {{ $reserva->fecha_reserva->format('d/m/Y') }} · {{ $reserva->reserva_inmediata ? 'Reserva inmediata' : 'Llegada '.substr($reserva->hora_inicio,0,5) }} · {{ $reserva->duracion_minutos }} min contratados</p>
@if($reserva->reserva_inmediata)
<div class="arrival-policy">@if($reserva->estado === 'confirmada' && !$reserva->estadia)<strong>Llega antes de las {{ $reserva->limite_llegada->format('H:i') }} del {{ $reserva->limite_llegada->format('d/m/Y') }}</strong><p>Tu pago está aprobado. Dispones de 15 minutos desde la aprobación.</p>@elseif($reserva->estado === 'pendiente_pago')<strong>15 minutos para llegar desde la aprobación del pago</strong><p>El plazo aún no ha empezado. Consulta aquí el resultado de la revisión.</p>@endif</div>
@endif
<div class="checkout-total"><small>Importe a pagar por adelantado</small><strong>S/ {{ number_format($reserva->monto_total,2) }}</strong></div>
@if($reserva->estado !== 'pendiente_pago')
<p>Estado de la reserva: <strong>{{ str_replace('_',' ',$reserva->estado) }}</strong>.</p>
@elseif($pago && $pago->estado === 'pendiente' && $pago->enviado_at)
<div class="alert-box">Pago pendiente de revisión. Tu espacio se mantiene reservado mientras el personal verifica el abono. No vuelvas a pagar.</div>
<p>Enviado: {{ $pago->enviado_at->format('d/m/Y H:i') }} · {{ strtoupper($pago->metodo_pago) }} · Operación {{ $pago->referencia_pago ?? 'adjunta en captura' }}</p>
@else
@if($pago && $pago->estado === 'rechazado')<div class="alert-box alert-error">Pago rechazado: {{ $pago->motivo_revision }}. Revisa los datos y presenta la evidencia corregida; no repitas el abono si ya pagaste.</div>@endif
<p>Envía la evidencia antes del {{ $reserva->expires_at?->format('d/m/Y H:i') }}. Al vencer el plazo sin envío, se libera la reserva.</p>
@if(!$configuracion->qr_yape && !$configuracion->qr_plin)
<div class="alert-box">El negocio todavía no ha configurado su QR de cobro. Comunícate con el personal antes de pagar.</div>
@else
<div class="payment-step"><span>1</span><h3>Paga al negocio</h3></div><p>Escanea el QR de tu aplicación y paga el importe indicado a <strong>{{ $configuracion->titular }}</strong> ({{ $configuracion->telefono }}).</p>
<div class="payment-qr-grid">
@foreach(['yape','plin'] as $medio)
@if($configuracion->{'qr_'.$medio})<div class="payment-qr-card"><h3>{{ strtoupper($medio) }}</h3><img src="{{ route('pagos.qr',$medio) }}" alt="QR de {{ strtoupper($medio) }} del negocio" style="width:220px;max-width:100%;height:auto"></div>@endif
@endforeach
</div>
<div class="payment-step"><span>2</span><h3>Envíanos la operación o captura</h3></div><p>Ingresa el número de operación o adjunta una captura del pago. El personal lo verificará en el celular del negocio.</p>
<form action="{{ route('pagos.enviar',$reserva) }}" method="POST" enctype="multipart/form-data">
@csrf
<div class="form-group"><label for="metodo_pago">Medio de pago</label><select id="metodo_pago" name="metodo_pago" class="form-control" required>
@foreach(['yape','plin'] as $medio) @if($configuracion->{'qr_'.$medio})<option value="{{ $medio }}" @selected(old('metodo_pago')===$medio)>{{ strtoupper($medio) }}</option>@endif @endforeach
</select></div>
<div class="form-group"><label for="referencia_pago">Número de operación</label><input id="referencia_pago" name="referencia_pago" class="form-control" maxlength="80" value="{{ old('referencia_pago') }}"></div>
<div class="form-group"><label for="comprobante">Captura del pago (JPG, PNG o WebP, máximo 5 MB)</label><input id="comprobante" type="file" name="comprobante" accept="image/jpeg,image/png,image/webp"></div>
<button class="btn btn-primary" type="submit">Enviar para revisión</button>
</form>
@endif
@endif
<p><a href="{{ route('reservas.index') }}" class="btn btn-secondary">Volver a mis reservas</a></p>
</div></div></section>
@endsection
