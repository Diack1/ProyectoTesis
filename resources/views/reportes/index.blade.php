@extends('layouts.admin')

@section('title', 'Reportes - Cochera Tentación')
@section('page-title', 'Reportes del sistema')
@section('page-subtitle', 'Consulta registros de ocupación, estados detectados y actividad histórica de espacios')

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
                Reporte de ocupación
            </h2>

            <p>
                Este módulo permite revisar los cambios de estado registrados por sensores,
                simulaciones o acciones manuales del administrador.
            </p>
        </div>

        <a href="{{ route('admin.reportes.exportarCsv', request()->query()) }}" class="btn btn-primary">
            Exportar CSV
        </a>
    </div>
</div>

<div class="section-block">
    <h2 class="section-title">Resumen general</h2>

    <div class="stats-grid-4">
        <div class="stat-card">
            <div class="stat-label">Total registros</div>
            <div class="stat-value text-info">{{ $totalRegistros ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Estados libres</div>
            <div class="stat-value text-success">{{ $totalLibres ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Estados ocupados</div>
            <div class="stat-value text-danger">{{ $totalOcupados ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Registros filtrados</div>
            <div class="stat-value">{{ method_exists($registros, 'total') ? $registros->total() : $registros->count() }}</div>
        </div>
    </div>
</div>

<div class="report-filters">
    <form action="{{ route('admin.reportes.index') }}" method="GET">
        <div class="report-filter-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label for="fecha_inicio">Fecha inicio</label>
                <input type="date"
                    name="fecha_inicio"
                    id="fecha_inicio"
                    class="form-control"
                    value="{{ request('fecha_inicio') }}">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="fecha_fin">Fecha fin</label>
                <input type="date"
                    name="fecha_fin"
                    id="fecha_fin"
                    class="form-control"
                    value="{{ request('fecha_fin') }}">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="estado">Estado detectado</label>
                <select name="estado" id="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="libre" {{ request('estado') === 'libre' ? 'selected' : '' }}>Libre</option>
                    <option value="ocupado" {{ request('estado') === 'ocupado' ? 'selected' : '' }}>Ocupado</option>
                    <option value="reservado" {{ request('estado') === 'reservado' ? 'selected' : '' }}>Reservado</option>
                    <option value="mantenimiento" {{ request('estado') === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="espacio_id">Espacio</label>
                <select name="espacio_id" id="espacio_id" class="form-control">
                    <option value="">Todos</option>

                    @foreach(($espacios ?? []) as $espacio)
                    <option value="{{ $espacio->id }}" {{ request('espacio_id') == $espacio->id ? 'selected' : '' }}>
                        {{ $espacio->codigo }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="report-actions">
                <button type="submit" class="btn btn-primary">
                    Filtrar
                </button>

                <a href="{{ route('admin.reportes.index') }}" class="btn btn-secondary">
                    Limpiar
                </a>
            </div>
        </div>
    </form>
</div>

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
                <th>Registro</th>
            </tr>
        </thead>

        <tbody>
            @forelse($registros as $registro)
            <tr>
                <td>
                    <strong>{{ $registro->espacio->codigo ?? '-' }}</strong><br>
                    <small class="text-muted">
                        {{ $registro->espacio->descripcion ?? '-' }}
                    </small>
                </td>

                <td>
                    {{ $registro->sensor->codigo_sensor ?? '-' }}
                </td>

                <td>
                    <span class="badge badge-{{ $registro->estado_detectado }}">
                        {{ str_replace('_', ' ', $registro->estado_detectado) }}
                    </span>
                </td>

                <td>
                    {{ $registro->distancia_cm !== null ? $registro->distancia_cm . ' cm' : '-' }}
                </td>

                <td>
                    @if($registro->origen)
                    <span class="badge badge-{{ $registro->origen }}">
                        {{ str_replace('_', ' ', $registro->origen) }}
                    </span>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>

                <td>
                    {{ $registro->fecha_hora ? \Carbon\Carbon::parse($registro->fecha_hora)->format('d/m/Y H:i:s') : '-' }}
                </td>

                <td>
                    {{ $registro->created_at ? $registro->created_at->format('d/m/Y H:i') : '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    No hay registros encontrados con los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">
    {{ $registros->links() }}
</div>

@endsection