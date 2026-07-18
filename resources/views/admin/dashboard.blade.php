@extends('layouts.admin')

@section('title', 'Dashboard - Cochera Tentación')
@section('page-title', 'Dashboard administrativo')
@section('page-subtitle', 'Resumen general de espacios, sensores, reservas, pagos y reembolsos')

@section('content')

<div class="section-block">
    <h2 class="section-title">Accesos rápidos</h2>

    <div class="quick-actions">
        <a href="{{ route('admin.monitoreo.index') }}" class="quick-card">
            <strong>Monitoreo</strong>
            <span>Revisar estados de espacios en tiempo real.</span>
        </a>

        <a href="{{ route('admin.espacios.index') }}" class="quick-card">
            <strong>Espacios</strong>
            <span>Gestionar espacios, estados y vehículos permitidos.</span>
        </a>

        <a href="{{ route('admin.reservas.index') }}" class="quick-card">
            <strong>Reservas</strong>
            <span>Ver reservas, pagos y solicitudes de reembolso.</span>
        </a>

        <a href="{{ route('admin.tarifas.index') }}" class="quick-card">
            <strong>Tarifas</strong>
            <span>Administrar tarifas por vehículo, horario y duración.</span>
        </a>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Disponibilidad de espacios</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total espacios</div>
            <div class="stat-value text-info">{{ $totalEspacios ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Libres</div>
            <div class="stat-value text-success">{{ $espaciosLibres ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Ocupados</div>
            <div class="stat-value text-danger">{{ $espaciosOcupados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Reservados</div>
            <div class="stat-value text-warning">{{ $espaciosReservados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Mantenimiento</div>
            <div class="stat-value">{{ $espaciosMantenimiento ?? 0 }}</div>
        </div>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Sensores y monitoreo</h2>

    <div class="stats-grid-4">
        <div class="stat-card">
            <div class="stat-label">Total sensores</div>
            <div class="stat-value text-info">{{ $totalSensores ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Sensores activos</div>
            <div class="stat-value text-success">{{ $sensoresActivos ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Sensores inactivos</div>
            <div class="stat-value text-danger">{{ $sensoresInactivos ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Registros históricos</div>
            <div class="stat-value">{{ $totalRegistros ?? 0 }}</div>
        </div>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Reservas</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total reservas</div>
            <div class="stat-value text-info">{{ $totalReservas ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pendientes de pago</div>
            <div class="stat-value text-warning">{{ $reservasPendientesPago ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Confirmadas</div>
            <div class="stat-value text-success">{{ $reservasConfirmadas ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Canceladas</div>
            <div class="stat-value text-danger">{{ $reservasCanceladas ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Finalizadas</div>
            <div class="stat-value">{{ $reservasFinalizadas ?? 0 }}</div>
        </div>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Pagos y reembolsos</h2>

    <div class="stats-grid-4">
        <div class="stat-card">
            <div class="stat-label">Pagos pendientes</div>
            <div class="stat-value text-warning">{{ $pagosPendientes ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pagos aprobados</div>
            <div class="stat-value text-success">{{ $pagosAprobados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pagos cancelados</div>
            <div class="stat-value text-danger">{{ $pagosCancelados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pagos reembolsados</div>
            <div class="stat-value">{{ $pagosReembolsados ?? 0 }}</div>
        </div>
    </div>

    <div class="stats-grid-4" style="margin-top:18px;">
        <div class="stat-card">
            <div class="stat-label">Reembolsos solicitados</div>
            <div class="stat-value text-warning">{{ $reembolsosSolicitados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Reembolsos aprobados</div>
            <div class="stat-value text-success">{{ $reembolsosAprobados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Reembolsos rechazados</div>
            <div class="stat-value text-danger">{{ $reembolsosRechazados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Usuarios registrados</div>
            <div class="stat-value text-info">{{ $totalUsuarios ?? 0 }}</div>
        </div>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Últimas reservas</h2>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Usuario</th>
                    <th>Espacio</th>
                    <th>Vehículo</th>
                    <th>Tarifa</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Estado</th>
                    <th>Monto</th>
                </tr>
            </thead>

            <tbody>
                @forelse($ultimasReservas as $reserva)
                <tr>
                    <td><strong>{{ $reserva->codigo_reserva }}</strong></td>
                    <td>{{ $reserva->usuario->name ?? '-' }}</td>
                    <td>{{ $reserva->espacio->codigo ?? '-' }}</td>
                    <td>{{ $reserva->tipo_vehiculo_nombre ?? '-' }}</td>
                    <td>{{ $reserva->tarifa_nombre ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($reserva->fecha_reserva)->format('d/m/Y') }}</td>
                    <td>{{ substr($reserva->hora_inicio, 0, 5) }} - {{ substr($reserva->hora_fin, 0, 5) }}</td>
                    <td>
                        <span class="badge badge-{{ $reserva->estado }}">
                            {{ str_replace('_', ' ', $reserva->estado) }}
                        </span>
                    </td>
                    <td><strong>S/ {{ number_format($reserva->monto_total, 2) }}</strong></td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">Todavía no existen reservas registradas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection