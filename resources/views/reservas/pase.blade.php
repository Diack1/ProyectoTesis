@extends('layouts.public')
@section('title', 'Tu reserva · Parke’o')
@section('content')
<section class="section"><div class="container"><div class="page-card">
    <h1>Tu reserva</h1>
    <h2>{{ $reserva->codigo_corto }}</h2>
    <p>Placa: <strong>{{ $reserva->placa }}</strong></p>
    <p>Espacio reservado: {{ $reserva->espacio->codigo }}</p>
    @if($reserva->estadia && $reserva->estadia->espacio_id !== $reserva->espacio_id)<p>Ubicación actual del vehículo: <strong>{{ $reserva->estadia->espacio->codigo }}</strong> (reubicado por el personal).</p>@endif
    <p>Tiempo contratado: {{ $reserva->duracion_minutos }} minutos</p>
    <p>Importe {{ $reserva->pagado_at ? 'pagado al llegar' : 'a pagar al llegar' }}: <strong>S/ {{ number_format($reserva->monto_total, 2) }}</strong></p>
    @if($qr)
        <p>Llega antes del <strong>{{ $reserva->expires_at->format('d/m/Y H:i:s') }}</strong>. El espacio se guarda durante 15 minutos desde que generas la reserva.</p>
        <img src="data:image/svg+xml;base64,{{ $qr }}" width="300" height="300" style="max-width:100%;height:auto;background:white" alt="QR de identificación de tu reserva">
        <p>Muestra este QR o el código al encargado. Él puede escanearlo con la cámara de su celular e iniciar sesión en Parke’o.</p>
        <p>El QR identifica tu reserva. El encargado debe recibir y verificar el pago antes de registrar la entrada.</p>
    @elseif(!$reserva->estadia)
        <p>Estado: {{ ucfirst(str_replace('_', ' ', $reserva->estado)) }}. Consulta con el encargado o genera una nueva reserva si venció.</p>
    @endif
    <section data-stay-clock data-url="{{ route('reservas.tiempo', $reserva) }}" class="page-card" aria-label="Tiempo estacionado">
        <h2>Tiempo estacionado</h2><p data-clock-status>Consultando estado…</p>
        <strong data-clock-time style="font-size:2rem">00:00:00</strong>
        <p>Extra estimado: <strong data-clock-extra>—</strong></p>
        <p>Tolerancia al superar el tiempo contratado: {{ $reserva->tolerancia_minutos }} minutos. Después: S/ {{ number_format($reserva->penalidad_por_fraccion, 2) }} por cada {{ $reserva->minutos_fraccion_snapshot }} minutos o fracción. El encargado confirma el importe final al salir.</p>
    </section>
    <a class="btn btn-secondary" href="{{ route('reservas.index') }}">Mis reservas</a>
</div></div></section>
@endsection
@push('scripts')<script src="{{ asset('js/reserva-tiempo.js') }}" defer></script>@endpush
