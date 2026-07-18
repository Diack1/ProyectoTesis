@extends('layouts.admin')

@section('title', 'Monitoreo - Cochera Tentación')
@section('page-title', 'Monitoreo de espacios')
@section('page-subtitle', 'Supervisión del estado actual de los espacios de la cochera')

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

<div class="section-block">
    <h2 class="section-title">Resumen de disponibilidad</h2>

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
    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
            <h2 class="section-title" style="margin-bottom:6px;">
                Estado de espacios
            </h2>
            <p class="text-muted" style="margin:0;">
                Desde aquí puedes cambiar manualmente el estado de un espacio o verificar el estado reportado por sensores.
            </p>
        </div>
    </div>

    <div class="admin-space-grid" style="margin-top:18px;">
        @forelse($espacios as $espacio)
        <div class="admin-space-card {{ $espacio->estado_actual }}">
            <div class="admin-space-header">
                <h3>{{ $espacio->codigo }}</h3>

                <span class="badge badge-{{ $espacio->estado_actual }}">
                    {{ str_replace('_', ' ', $espacio->estado_actual) }}
                </span>
            </div>

            <p class="admin-space-desc">
                {{ $espacio->descripcion }}
            </p>

            <p class="text-muted">
                <strong>Estado actual:</strong>
                {{ str_replace('_', ' ', $espacio->estado_actual) }}
            </p>

            @if(isset($espacio->sensor))
            <p class="text-muted">
                <strong>Sensor:</strong>
                {{ $espacio->sensor->codigo_sensor ?? '-' }}
            </p>
            @endif

            <div class="admin-space-actions">
                <form action="{{ route('admin.espacios.estado', $espacio) }}" method="POST">
                    @csrf
                    <input type="hidden" name="estado" value="libre">
                    <button type="submit" class="btn btn-success btn-sm">
                        Libre
                    </button>
                </form>

                <form action="{{ route('admin.espacios.estado', $espacio) }}" method="POST">
                    @csrf
                    <input type="hidden" name="estado" value="ocupado">
                    <button type="submit" class="btn btn-danger btn-sm">
                        Ocupado
                    </button>
                </form>

                <form action="{{ route('admin.espacios.estado', $espacio) }}" method="POST">
                    @csrf
                    <input type="hidden" name="estado" value="reservado">
                    <button type="submit" class="btn btn-warning btn-sm">
                        Reservado
                    </button>
                </form>

                <form action="{{ route('admin.espacios.estado', $espacio) }}" method="POST">
                    @csrf
                    <input type="hidden" name="estado" value="mantenimiento">
                    <button type="submit" class="btn btn-maintenance btn-sm">
                        Mantenimiento
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="card">
            No hay espacios registrados.
        </div>
        @endforelse
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Últimos registros de ocupación</h2>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Espacio</th>
                    <th>Sensor</th>
                    <th>Estado detectado</th>
                    <th>Distancia</th>
                    <th>Origen</th>
                    <th>Fecha y hora</th>
                </tr>
            </thead>

            <tbody>
                @forelse(($registros ?? []) as $registro)
                <tr>
                    <td>{{ $registro->espacio->codigo ?? '-' }}</td>
                    <td>{{ $registro->sensor->codigo_sensor ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $registro->estado_detectado }}">
                            {{ str_replace('_', ' ', $registro->estado_detectado) }}
                        </span>
                    </td>
                    <td>
                        {{ $registro->distancia_cm !== null ? $registro->distancia_cm . ' cm' : '-' }}
                    </td>
                    <td>{{ $registro->origen ?? '-' }}</td>
                    <td>
                        {{ $registro->fecha_hora ? \Carbon\Carbon::parse($registro->fecha_hora)->format('d/m/Y H:i:s') : '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">Todavía no existen registros de ocupación.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection