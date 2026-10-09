@extends('layouts.admin')
@section('title','Reservas - Parke’o')
@section('page-title','Reservas')
@section('page-subtitle','Consulta la llegada y el pago antes de registrar la entrada.')
@section('content')
@include('partials.payment-feedback')
@include('admin.reservas.tabs')
<div class="management-stats"><x-admin.stat label="Llegadas pendientes de hoy" :value="$llegadasHoy" icon="car"/><x-admin.stat label="Próximas llegadas" :value="$proximas" icon="clock"/><x-admin.stat label="Reembolsos solicitados" :value="$reembolsosSolicitados" icon="wallet"/></div>

<form class="filters card" method="get"><input type="hidden" name="vista" value="{{ request('vista','hoy') }}"><div class="form-group"><label for="buscar">Cliente, placa o reserva</label><input id="buscar" name="buscar" value="{{ request('buscar') }}" maxlength="100"></div><button class="btn btn-secondary">Buscar</button></form>
<section class="admin-page-card"><h2 class="management-heading">{{ request('vista') === 'historial' ? 'Historial y reembolsos' : 'Reservas pendientes de llegada' }}</h2><div class="table-responsive"><table class="data-table mobile-records"><thead><tr><th>Cliente / reserva</th><th>Vehículo / llegada</th><th>Pago</th><th>Acción</th></tr></thead><tbody>@forelse($reservas as $reserva)
@php
$ultimoPago = $reserva->pagos->sortByDesc('id')->first();
$ultimoReembolso = $reserva->reembolsos->sortByDesc('id')->first();
$pagada = $reserva->pagos->contains('estado','aprobado');
@endphp
<tr><td data-label="Cliente / reserva"><strong>{{ $reserva->usuario?->name ?? 'Cliente' }}</strong><small>{{ $reserva->codigo_corto }}</small>
<details><summary>Ver detalles de la reserva</summary><p>{{ $reserva->codigo_corto }}<br>{{ $reserva->usuario?->email }}<br>{{ $reserva->tipo_vehiculo_nombre }} · {{ $reserva->tarifa_nombre }}<br>{{ $reserva->duracion_minutos }} minutos contratados · S/ {{ number_format($reserva->monto_total,2) }}<br>Tolerancia de exceso: {{ $reserva->tolerancia_minutos }} min · Penalidad por fracción: S/ {{ number_format($reserva->penalidad_por_fraccion,2) }}</p><p>Pago: {{ $ultimoPago?->estado ?? 'Sin pago' }} · {{ $ultimoPago?->metodo_pago }}</p>@if($ultimoReembolso)<p>Reembolso: {{ $ultimoReembolso->estado }} · S/ {{ number_format($ultimoReembolso->monto,2) }}<br>{{ $ultimoReembolso->motivo }}</p>@endif</details></td><td data-label="Vehículo / llegada"><span class="plate-tag">{{ $reserva->placa ?? 'Sin placa' }}</span><small>Espacio {{ $reserva->espacio?->codigo }}</small><small>{{ $reserva->estadia ? 'Llegada registrada' : ucfirst(str_replace('_', ' ', $reserva->estado)) }}</small>@if($reserva->estado === 'confirmada' && !$reserva->estadia)<small>Límite de llegada: {{ $reserva->limite_llegada->format('d/m/Y H:i') }}</small>@else<small>{{ $reserva->fecha_reserva->format('d/m/Y') }}</small>@endif</td><td data-label="Pago"><span class="badge {{ $pagada ? 'badge-success' : 'badge-warning' }}">{{ $ultimoPago?->estado === 'reembolsado' ? 'Reembolsado' : ($pagada ? 'Aprobado' : ($ultimoPago?->estado === 'pendiente' ? ($ultimoPago->enviado_at ? 'Por revisar' : 'Pendiente de pago') : ($ultimoPago?->estado ? ucfirst($ultimoPago->estado) : 'Sin pago'))) }}</span><small>S/ {{ number_format($reserva->monto_total, 2) }}</small></td>
<td data-label="Acción"><div class="reservation-payment">
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

</div></td></tr>
@empty<tr><td colspan="4">No hay reservas para esta consulta.</td></tr>@endforelse</tbody></table></div></section>
{{ $reservas->links() }}
@endsection
