@extends('layouts.admin')

@section('title', 'Administradores - Cochera Tentación')
@section('page-title', 'Gestión de administradores')
@section('page-subtitle', 'Crea, revisa y desactiva cuentas administrativas del sistema')

@section('content')

@php
$listaAdmins = $admins ?? $usuarios ?? collect();
@endphp

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
                Administradores registrados
            </h2>

            <p>
                Desde este módulo el superadministrador puede crear cuentas administrativas
                y controlar su acceso al sistema.
            </p>
        </div>

        @if(Route::has('superadmin.admins.create'))
        <a href="{{ route('superadmin.admins.create') }}" class="btn btn-primary">
            + Nuevo administrador
        </a>
        @endif
    </div>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Fecha registro</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($listaAdmins as $admin)
            <tr>
                <td>
                    <strong>{{ $admin->name }}</strong>
                </td>

                <td>
                    {{ $admin->email }}
                </td>

                <td>
                    <span class="role-badge role-{{ $admin->role }}">
                        {{ str_replace('_', ' ', $admin->role) }}
                    </span>
                </td>

                <td>
                    @if($admin->activo)
                    <span class="user-status-active">Activo</span>
                    @else
                    <span class="user-status-inactive">Inactivo</span>
                    @endif
                </td>

                <td>
                    {{ $admin->created_at ? $admin->created_at->format('d/m/Y') : '-' }}
                </td>

                <td>
                    <div class="table-actions">
                        @if($admin->id !== auth()->id())
                        <form action="{{ route('superadmin.admins.toggleActivo', $admin) }}" method="POST"
                            onsubmit="return confirm('¿Seguro que deseas cambiar el estado de este administrador?');">
                            @csrf
                            @method('PATCH')

                            @if($admin->activo)
                            <button type="submit" class="btn btn-danger btn-sm">
                                Desactivar
                            </button>
                            @else
                            <button type="submit" class="btn btn-success btn-sm">
                                Activar
                            </button>
                            @endif
                        </form>
                        @else
                        <span class="text-muted">Cuenta actual</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    No hay administradores registrados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($listaAdmins, 'links'))
<div style="margin-top:18px;">
    {{ $listaAdmins->links() }}
</div>
@endif

@endsection