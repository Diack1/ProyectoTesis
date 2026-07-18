@extends('layouts.admin')

@section('title', 'Tarifas - Cochera Tentación')
@section('page-title', 'Gestión de tarifas')
@section('page-subtitle', 'Administra tarifas por vehículo, horario, duración, tolerancia y penalidad')

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
                Tarifas registradas
            </h2>

            <p>
                Desde este módulo puedes administrar tarifas por tipo de vehículo, por hora,
                fracción, diaria o nocturna. Estas tarifas serán usadas automáticamente al generar reservas.
            </p>
        </div>

        <a href="{{ route('admin.tarifas.create') }}" class="btn btn-primary">
            + Nueva tarifa
        </a>
    </div>
</div>

<div class="filters-card">
    <form action="{{ route('admin.tarifas.index') }}" method="GET">
        <div class="filters-grid">
            <div class="form-group" style="margin-bottom:0;">
                <label for="vehiculo_tipo_id">Vehículo</label>
                <select name="vehiculo_tipo_id" id="vehiculo_tipo_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($vehiculoTipos as $tipo)
                    <option value="{{ $tipo->id }}" {{ request('vehiculo_tipo_id') == $tipo->id ? 'selected' : '' }}>
                        {{ $tipo->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="tipo_tarifa">Tipo de tarifa</label>
                <select name="tipo_tarifa" id="tipo_tarifa" class="form-control">
                    <option value="">Todas</option>
                    <option value="por_hora" {{ request('tipo_tarifa') === 'por_hora' ? 'selected' : '' }}>Por hora</option>
                    <option value="fraccion" {{ request('tipo_tarifa') === 'fraccion' ? 'selected' : '' }}>Fracción</option>
                    <option value="diaria" {{ request('tipo_tarifa') === 'diaria' ? 'selected' : '' }}>Diaria</option>
                    <option value="nocturna" {{ request('tipo_tarifa') === 'nocturna' ? 'selected' : '' }}>Nocturna</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="activo">Estado</label>
                <select name="activo" id="activo" class="form-control">
                    <option value="">Todos</option>
                    <option value="1" {{ request('activo') === '1' ? 'selected' : '' }}>Activas</option>
                    <option value="0" {{ request('activo') === '0' ? 'selected' : '' }}>Inactivas</option>
                </select>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">
                    Filtrar
                </button>

                <a href="{{ route('admin.tarifas.index') }}" class="btn btn-secondary">
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
                <th>Nombre</th>
                <th>Vehículo</th>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Fracción</th>
                <th>Condiciones</th>
                <th>Horario</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($tarifas as $tarifa)
            <tr>
                <td>
                    <strong>{{ $tarifa->nombre }}</strong>
                </td>

                <td>
                    {{ $tarifa->vehiculoTipo->nombre ?? '-' }}
                </td>

                <td>
                    <span class="badge badge-{{ $tarifa->tipo_tarifa }}">
                        {{ str_replace('_', ' ', $tarifa->tipo_tarifa) }}
                    </span>
                </td>

                <td>
                    <div class="tarifa-detail">
                        <strong>Base:</strong> S/ {{ number_format($tarifa->monto_base, 2) }}<br>
                        <strong>Hora:</strong> S/ {{ number_format($tarifa->monto_por_hora, 2) }}
                    </div>
                </td>

                <td>
                    @if($tarifa->monto_por_fraccion && $tarifa->minutos_fraccion)
                    <div class="tarifa-detail">
                        <strong>S/ {{ number_format($tarifa->monto_por_fraccion, 2) }}</strong><br>
                        cada {{ $tarifa->minutos_fraccion }} min
                    </div>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>

                <td>
                    <div class="tarifa-detail">
                        <strong>Mínimo:</strong> {{ $tarifa->tiempo_minimo_minutos }} min<br>
                        <strong>Tolerancia:</strong> {{ $tarifa->tolerancia_minutos }} min<br>
                        <strong>Penalidad:</strong> S/ {{ number_format($tarifa->penalidad_por_fraccion, 2) }}
                    </div>
                </td>

                <td>
                    @if($tarifa->hora_inicio && $tarifa->hora_fin)
                    {{ substr($tarifa->hora_inicio, 0, 5) }}
                    -
                    {{ substr($tarifa->hora_fin, 0, 5) }}
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>

                <td>
                    {{ $tarifa->prioridad }}
                </td>

                <td>
                    @if($tarifa->activo)
                    <span class="badge badge-activa">Activa</span>
                    @else
                    <span class="badge badge-inactiva">Inactiva</span>
                    @endif
                </td>

                <td>
                    <div class="table-actions">
                        <a href="{{ route('admin.tarifas.edit', $tarifa) }}" class="btn btn-warning btn-sm">
                            Editar
                        </a>

                        @if(!$tarifa->activo)
                        <form action="{{ route('admin.tarifas.activar', $tarifa) }}" method="POST">
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="btn btn-success btn-sm">
                                Activar
                            </button>
                        </form>
                        @else
                        <form action="{{ route('admin.tarifas.desactivar', $tarifa) }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas desactivar esta tarifa?');">
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="btn btn-danger btn-sm">
                                Desactivar
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">
                    No hay tarifas registradas.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">
    {{ $tarifas->links() }}
</div>

@endsection