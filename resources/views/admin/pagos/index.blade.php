@extends('layouts.admin')
@section('page-title', 'Revisión de pagos')
@section('page-subtitle', 'Verifica cada abono en el celular del negocio antes de aprobarlo')
@section('content')
@include('partials.payment-feedback')
@forelse($pagos as $pago)
<article class="admin-page-card">
<h2>{{ $pago->reserva->codigo_reserva }} · S/ {{ number_format($pago->monto,2) }}</h2>
<p>{{ $pago->usuario->name }} · {{ strtoupper($pago->metodo_pago) }} · Espacio {{ $pago->reserva->espacio->codigo }}</p>
<p>Operación: <strong>{{ $pago->referencia_pago ?? 'Consultar captura' }}</strong> · Enviado {{ $pago->enviado_at->format('d/m/Y H:i') }}</p>
<p>Estado: <strong>{{ $pago->estado }}</strong></p>
@if($pago->comprobante)<p><a class="btn btn-secondary" href="{{ route('pagos.comprobante',$pago) }}" target="_blank" rel="noopener">Ver captura del pago</a></p>@endif
@if($pago->estado === 'pendiente' && $pago->reserva->estado === 'pendiente_pago')
<form action="{{ route('admin.pagos.revisar',$pago) }}" method="POST">@csrf
<div class="form-group"><label for="motivo-{{ $pago->id }}">Observación (obligatoria al rechazar)</label><textarea id="motivo-{{ $pago->id }}" name="motivo_revision" class="form-control" maxlength="1000"></textarea></div>
<button name="decision" value="aprobado" class="btn btn-success">Abono verificado: aprobar</button>
<button name="decision" value="rechazado" class="btn btn-danger">Rechazar</button>
</form>
@else
<p>{{ $pago->motivo_revision }} · Revisado {{ $pago->revisado_at?->format('d/m/Y H:i') ?? '—' }} · Responsable #{{ $pago->revisado_por ?? '—' }}</p>
@endif
</article>
@empty<div class="admin-page-card">Todavía no se han presentado pagos.</div>@endforelse
{{ $pagos->links() }}
@endsection
