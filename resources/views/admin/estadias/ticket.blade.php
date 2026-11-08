<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $estadia->codigo_ticket }}</title>@vite('resources/css/pages/ticket.css')<style>@if(request()->boolean('incrustado'))body{margin:0;background:transparent}.actions{display:none}main{width:100%;max-width:100%;margin:0;padding:12px}@endif @media print{@page{size:{{ (int)request('papel',80)===58?'58mm':'80mm' }} auto;margin:3mm}}</style><script src="{{ asset('js/parkeo.js') }}" defer></script></head>
<body><div class="actions"><button type="button" data-print>Imprimir</button><a href="{{ route('admin.estadias.show',$estadia) }}">Volver al ticket</a></div>
<main><h1>{{ $negocio->razon_social ?: ($negocio->titular ?: 'Parke’o') }}</h1><p>{{ $negocio->direccion_fiscal ?: 'Huánuco · Perú' }} · Estacionamiento</p>
@if($negocio->ruc)<p>RUC: {{ $negocio->ruc }}</p>@endif
@if($negocio->telefono)<p>Teléfono: {{ $negocio->telefono }}</p>@endif
<h2>{{ $estadia->hora_salida ? ucfirst($estadia->tipo_comprobante ?? 'boleta').' · Comprobante de pago' : 'Ticket de ingreso' }}</h2>
<p>{{ $ventaActual?->numero ?? $estadia->codigo_ticket }}</p><strong class="plate">{{ $estadia->placa }}</strong>
@unless($estadia->hora_salida)<img class="ticket-qr" src="data:image/svg+xml;base64,{{ base64_encode(app(\App\Services\TicketQrService::class)->svg($estadia)) }}" width="180" height="180" alt="QR seguro del ticket"><p>El QR identifica el ticket y no acredita un pago.</p>@endunless
<dl>
    @if($estadia->hora_salida)
    <dt>Cliente</dt><dd>{{ $ventaActual?->cliente ?? ($estadia->clienteCobro ? $estadia->clienteCobro->nombres.' '.$estadia->clienteCobro->apellidos : ($estadia->reserva?->usuario?->name ?? 'Cliente general')) }}</dd>
    <dt>Documento</dt><dd>{{ $ventaActual?->documento ?? ($estadia->clienteCobro ? $estadia->clienteCobro->tipo_documento.' '.$estadia->clienteCobro->numero_documento : '—') }}</dd>
    <dt>Atendido por</dt><dd>{{ $estadia->operadorSalida?->name ?? 'Recepción' }}</dd>
    @endif
    <dt>Placa</dt><dd>{{ $estadia->placa }}</dd><dt>Espacio</dt><dd>{{ $estadia->espacio->codigo }}</dd>
    @if(!$estadia->hora_salida || $estadia->tipo_impresion !== 'consumo')
    <dt>Vehículo</dt><dd>{{ $estadia->vehiculoTipo->nombre }}</dd><dt>Ingreso</dt><dd>{{ $estadia->hora_ingreso->format('d/m/Y H:i:s') }}</dd>
    <dt>Inicio del cobro</dt><dd>{{ $estadia->inicio_cobro?->format('d/m/Y H:i:s') ?? 'Al detectar el sensor el vehículo estacionado' }}</dd>
    <dt>Tarifa</dt><dd>{{ $ventaActual?->datos['tarifa'] ?? ($estadia->tarifa_snapshot['nombre'] ?? 'Tarifa registrada') }}</dd>
    @endif
    @if($estadia->reserva_id)<dt>Tiempo contratado</dt><dd>{{ $estadia->minutos_contratados }} min</dd><dt>Adelanto</dt><dd>S/ {{ number_format($estadia->monto_adelantado,2) }}</dd>@endif
    @if($estadia->hora_salida)
    <dt>Salida</dt><dd>{{ $estadia->hora_salida->format('d/m/Y H:i:s') }}</dd><dt>Tiempo utilizado</dt><dd>{{ $estadia->minutos_cobrados }} min</dd>
    @if($estadia->tipo_impresion !== 'consumo')
    <dt>Descuento</dt><dd>{{ number_format($estadia->descuento_porcentaje,2) }}% + S/ {{ number_format($estadia->descuento_monto,2) }}{{ $estadia->motivo_descuento ? ' · '.$estadia->motivo_descuento : '' }}</dd>
    @endif
    <dt>Total</dt><dd>S/ {{ number_format($ventaActual?->total ?? $estadia->monto_total,2) }}</dd>
    @foreach($estadia->pagosSalida->whereIn('estado',['aprobado','reembolsado']) as $pago)<dt>{{ ucfirst($pago->metodo_pago) }}</dt><dd>S/ {{ number_format($pago->monto,2) }}@if($pago->metodo_pago === 'efectivo') · Recibido S/ {{ number_format($pago->efectivo_recibido,2) }}@endif</dd>@endforeach
    @if($estadia->pagosSalida->whereIn('estado',['aprobado','reembolsado'])->sum('vuelto')>0)<dt>Vuelto</dt><dd>S/ {{ number_format($estadia->pagosSalida->whereIn('estado',['aprobado','reembolsado'])->sum('vuelto'),2) }}</dd>@endif
    @endif
</dl>
<p>{{ $estadia->hora_salida ? 'Gracias por tu visita.' : 'Conserva y entrega este ticket al salir. El pago se realiza según el tiempo utilizado y la tarifa registrada.' }}</p>
</main>@if(request()->boolean('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif</body></html>
