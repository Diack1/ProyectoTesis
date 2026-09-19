@extends('layouts.admin')
@section('page-title', 'Configuración de cobros')
@section('content')
@include('partials.payment-feedback')
<div class="form-card"><p>Sube el QR fijo del negocio para cada aplicación que aceptes. Puedes usar la misma imagen si tu QR admite ambas.</p>
<form action="{{ route('admin.pagos.configuracion.update') }}" method="POST" enctype="multipart/form-data">@csrf @method('PUT')
<div class="form-group"><label for="titular">Titular que verá el cliente</label><input id="titular" name="titular" class="form-control" required value="{{ old('titular',$configuracion->titular) }}"></div>
<div class="form-group"><label for="telefono">Celular del negocio</label><input id="telefono" name="telefono" class="form-control" required value="{{ old('telefono',$configuracion->telefono) }}"></div>
<div class="form-group"><label for="minutos_pago">Minutos para enviar el pago</label><input id="minutos_pago" type="number" name="minutos_pago" class="form-control" min="5" max="60" required value="{{ old('minutos_pago',$configuracion->minutos_pago) }}"><p>Se aplica a nuevas reservas y a pagos rechazados. Los pagos enviados mantienen su reserva hasta la revisión.</p></div>
<div class="form-group"><label for="tolerancia_llegada">Tolerancia de llegada a una reserva pagada (minutos)</label><input id="tolerancia_llegada" type="number" name="tolerancia_llegada" min="10" max="15" required value="{{ old('tolerancia_llegada',$configuracion->tolerancia_llegada) }}"><p>Se aplica a nuevas reservas desde la hora de llegada indicada. Si el cliente no llega, se libera el espacio y el pago pasa a revisión para un posible reembolso manual.</p></div>
@foreach(['yape','plin'] as $medio)
<div class="form-group"><label for="qr_{{ $medio }}">QR {{ strtoupper($medio) }} (máximo 4 MB)</label>
@if($configuracion->{'qr_'.$medio})<p><img src="{{ route('pagos.qr',$medio) }}" alt="QR {{ $medio }} actual" style="width:160px;max-width:100%"></p>@endif
<input id="qr_{{ $medio }}" type="file" name="qr_{{ $medio }}" accept="image/jpeg,image/png,image/webp"></div>
@endforeach
<button class="btn btn-primary">Guardar configuración</button></form></div>
@endsection
