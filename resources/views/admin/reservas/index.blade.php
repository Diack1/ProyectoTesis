@extends('layouts.admin')
@section('title','Reservas - Parke’o')
@section('page-title','Reservas')
@section('page-subtitle','Consulta la llegada y el pago antes de registrar la entrada.')
@section('content')
@include('partials.payment-feedback')
@include('admin.reservas.tabs')
<form class="filters card" method="get"><input type="hidden" name="vista" value="{{ request('vista','hoy') }}"><div class="form-group"><label for="buscar">Cliente, placa o reserva</label><input id="buscar" name="buscar" value="{{ request('buscar') }}" maxlength="100"></div><button class="btn btn-secondary">Buscar</button></form>
<div class="reservation-cards">@forelse($reservas as $reserva)
@php
$ultimoPago = $reserva->pagos->sortByDesc('id')->first();
$ultimoReembolso = $reserva->reembolsos->sortByDesc('id')->first();
$pagada = $reserva->pagos->contains('estado','aprobado');
@endphp
<article class="reservation-item"><div><span class="badge">{{ $reserva->estado === 'pendiente_pago' && !$reserva->expires_at ? 'Pago en revisión' : ucfirst(str_replace('_',' ',$reserva->estado)) }}</span><h2>{{ $reserva->placa ?? 'Sin placa registrada' }} · {{ $reserva->espacio?->codigo }}</h2><p><strong>{{ $reserva->usuario?->name }}</strong></p><p>Llegada prevista: {{ $reserva->fecha_reserva->format('d/m/Y') }} · {{ substr($reserva->hora_inicio,0,5) }}</p>
<details><summary>Ver detalles de la reserva</summary><p>{{ $reserva->codigo_reserva }}<br>{{ $reserva->usuario?->email }}<br>{{ $reserva->tipo_vehiculo_nombre }} · {{ $reserva->tarifa_nombre }}<br>{{ $reserva->duracion_minutos }} minutos contratados · S/ {{ number_format($reserva->monto_total,2) }}<br>Tolerancia de exceso: {{ $reserva->tolerancia_minutos }} min · Penalidad por fracción: S/ {{ number_format($reserva->penalidad_por_fraccion,2) }}</p><p>Pago: {{ $ultimoPago?->estado ?? 'Sin pago' }} · {{ $ultimoPago?->metodo_pago }}</p>@if($ultimoReembolso)<p>Reembolso: {{ $ultimoReembolso->estado }} · S/ {{ number_format($ultimoReembolso->monto,2) }}<br>{{ $ultimoReembolso->motivo }}</p>@endif</details></div>
<div class="reservation-payment">
@if($reserva->estadia)<a class="btn btn-secondary" href="{{ route('admin.estadias.show',$reserva->estadia) }}">Ver ticket</a>
@elseif($reserva->estado==='confirmada' && $pagada && !$reserva->inasistencia_at)<a class="btn btn-primary" href="{{ route('admin.estadias.create',['reserva'=>$reserva->id]) }}">Registrar llegada</a>
@elseif($reserva->estado==='pendiente_pago' && !$reserva->expires_at)<a class="btn btn-primary" href="{{ route('admin.pagos.index') }}">Revisar pago</a>
@elseif($reserva->estado==='pendiente_pago')<p>Esperando el pago del cliente. La reserva todavía no está confirmada.</p>
@endif
                        @if($ultimoReembolso && $ultimoReembolso->estado === 'solicitado' && auth()->user()->esSuperAdmin())
                        <form action="{{ route('admin.reembolsos.aprobar', $ultimoReembolso) }}" method="POST"
                            onsubmit="return confirm('¿Confirmas que ya devolviste el dinero al cliente?');">
                            @csrf

                            <button type="submit" class="btn btn-success btn-sm">
                                Registrar devolución realizada
                            </button>
                        </form>

                        <form action="{{ route('admin.reembolsos.rechazar', $ultimoReembolso) }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas rechazar este reembolso?');">
                            @csrf

                            <button type="submit" class="btn btn-danger btn-sm">
                                Rechazar
                            </button>
                        </form>
                        @else

                        @endif

</div></article>
@empty<div class="card">No hay reservas para esta consulta.</div>@endforelse</div>
{{ $reservas->links() }}
@endsection
