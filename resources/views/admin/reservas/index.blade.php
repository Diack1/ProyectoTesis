@extends('layouts.admin')

@section('title', 'Reservas - Cochera Tentación')
@section('page-title', 'Administración de reservas')
@section('page-subtitle', 'Consulta reservas, pagos y solicitudes de reembolso registradas en el sistema')

@section('content')

@if(session('success'))
<div class="alert-box alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert-box alert-error">
    {{ session('error') }}
</div>
@endif

<div class="admin-page-card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
            <h2 class="section-title" style="margin-bottom:6px;">
                Reservas registradas
            </h2>

            <p>
                Desde este módulo el administrador puede revisar las reservas de todos los usuarios,
                verificar pagos y atender solicitudes de reembolso.
            </p>
        </div>
    </div>
</div>

<div class="reservation-summary">
    <div class="reservation-summary-card">
        <span>Total reservas</span>
        <strong>{{ $reservas->total() ?? $reservas->count() }}</strong>
    </div>

    <div class="reservation-summary-card">
        <span>Pendientes de pago</span>
        <strong class="text-warning">
            {{ $reservas->where('estado', 'pendiente_pago')->count() }}
        </strong>
    </div>

    <div class="reservation-summary-card">
        <span>Confirmadas</span>
        <strong class="text-success">
            {{ $reservas->where('estado', 'confirmada')->count() }}
        </strong>
    </div>

    <div class="reservation-summary-card">
        <span>Canceladas / Expiradas</span>
        <strong class="text-danger">
            {{ $reservas->whereIn('estado', ['cancelada', 'expirada'])->count() }}
        </strong>
    </div>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Usuario</th>
                <th>Espacio</th>
                <th>Vehículo</th>
                <th>Tarifa aplicada</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Estado</th>
                <th>Monto</th>
                <th>Condiciones</th>
                <th>Pago</th>
                <th>Reembolso</th>
                <th>Acción</th>
            </tr>
        </thead>

        <tbody>
            @forelse($reservas as $reserva)
            @php
            $ultimoPago = $reserva->pagos->sortByDesc('created_at')->first();
            $ultimoReembolso = $reserva->reembolsos->sortByDesc('created_at')->first();
            @endphp

            <tr>
                <td>
                    <strong>{{ $reserva->codigo_reserva }}</strong>
                </td>

                <td>
                    <strong>{{ $reserva->usuario->name ?? '-' }}</strong><br>
                    <small class="text-muted">
                        {{ $reserva->usuario->email ?? '-' }}
                    </small>
                </td>

                <td>
                    {{ $reserva->espacio->codigo ?? '-' }}
                </td>

                <td>
                    {{ $reserva->tipo_vehiculo_nombre ?? '-' }}
                </td>

                <td>
                    <strong>{{ $reserva->tarifa_nombre ?? '-' }}</strong><br>
                    <small class="text-muted">
                        {{ $reserva->tipo_tarifa ? str_replace('_', ' ', $reserva->tipo_tarifa) : '-' }}
                    </small>
                </td>

                <td>
                    {{ \Carbon\Carbon::parse($reserva->fecha_reserva)->format('d/m/Y') }}
                </td>

                <td>
                    {{ substr($reserva->hora_inicio, 0, 5) }}
                    -
                    {{ substr($reserva->hora_fin, 0, 5) }}
                </td>

                <td>
                    <span class="badge badge-{{ $reserva->estado }}">
                        {{ str_replace('_', ' ', $reserva->estado) }}
                    </span>
                </td>

                <td>
                    <strong>S/ {{ number_format($reserva->monto_total, 2) }}</strong>
                </td>

                <td>
                    <small class="text-muted">
                        Tolerancia: {{ $reserva->tolerancia_minutos ?? 0 }} min<br>
                        Penalidad: S/ {{ number_format($reserva->penalidad_por_fraccion ?? 0, 2) }}<br>
                        Monto penalidad: S/ {{ number_format($reserva->monto_penalidad ?? 0, 2) }}
                    </small>
                </td>

                <td>
                    @if($ultimoPago)
                    <span class="payment-status payment-{{ $ultimoPago->estado }}">
                        {{ str_replace('_', ' ', $ultimoPago->estado) }}
                    </span>

                    <br>

                    <small class="text-muted">
                        {{ $ultimoPago->metodo_pago ?? '-' }}
                    </small>
                    @else
                    <span class="text-muted">Sin pago</span>
                    @endif
                </td>

                <td>
                    @if($ultimoReembolso)
                    <div class="refund-box">
                        <strong>{{ str_replace('_', ' ', $ultimoReembolso->estado) }}</strong><br>
                        Monto: S/ {{ number_format($ultimoReembolso->monto, 2) }}
                    </div>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>

                <td>
                    <div class="table-actions">
                        @if($ultimoReembolso && $ultimoReembolso->estado === 'solicitado')
                        <form action="{{ route('admin.reembolsos.aprobar', $ultimoReembolso) }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas aprobar este reembolso?');">
                            @csrf

                            <button type="submit" class="btn btn-success btn-sm">
                                Aprobar
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
                        <span class="text-muted">-</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="13">
                    No hay reservas registradas.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">
    {{ $reservas->links() }}
</div>

@endsection