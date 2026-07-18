@extends('layouts.admin')

@section('title', 'Espacios - Cochera Tentación')
@section('page-title', 'Gestión de espacios')
@section('page-subtitle', 'Administra los espacios de estacionamiento, estados y vehículos permitidos')

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
                Espacios registrados
            </h2>

            <p>
                Desde aquí puedes crear, editar o desactivar espacios de la cochera.
                También puedes definir si permiten auto, moto o ambos.
            </p>
        </div>

        <a href="{{ route('admin.espacios.create') }}" class="btn btn-primary">
            + Nuevo espacio
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Estado actual</th>
                <th>Vehículos permitidos</th>
                <th>Activo</th>
                <th>Fecha registro</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($espacios as $espacio)
            <tr>
                <td>
                    <strong>{{ $espacio->codigo }}</strong>
                </td>

                <td>
                    {{ $espacio->descripcion }}
                </td>

                <td>
                    <span class="badge badge-{{ $espacio->estado_actual }}">
                        {{ str_replace('_', ' ', $espacio->estado_actual) }}
                    </span>
                </td>

                <td>
                    <div class="vehicle-tags">
                        @forelse($espacio->vehiculoTipos as $tipo)
                        <span class="vehicle-tag">
                            {{ $tipo->nombre }}
                        </span>
                        @empty
                        <span class="text-muted">
                            Sin asignar
                        </span>
                        @endforelse
                    </div>
                </td>

                <td>
                    @if($espacio->activo)
                    <span class="status-active">Activo</span>
                    @else
                    <span class="status-inactive">Inactivo</span>
                    @endif
                </td>

                <td>
                    {{ $espacio->created_at ? $espacio->created_at->format('d/m/Y') : '-' }}
                </td>

                <td>
                    <div class="table-actions">
                        <a href="{{ route('admin.espacios.edit', $espacio) }}" class="btn btn-warning btn-sm">
                            Editar
                        </a>

                        <form action="{{ route('admin.espacios.destroy', $espacio) }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas eliminar o desactivar este espacio?');">
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="btn btn-danger btn-sm">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    No hay espacios registrados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">
    {{ $espacios->links() }}
</div>

@endsection