@extends('layouts.admin')

@section('title', 'Administradores - Parke’o')
@section('page-title', 'Personal')
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
                Personal registrado
            </h2>

            <p>
                Desde este módulo el superadministrador puede crear cuentas administrativas
                y controlar su acceso al sistema.
            </p>
        </div>

        @if(Route::has('superadmin.admins.create'))
        <a href="{{ route('superadmin.admins.create') }}" class="btn btn-primary">
            + Nueva cuenta
        </a>
        @endif
    </div>
</div>

<div class="management-stats"><x-admin.stat label="Personal registrado" :value="$totalPersonal" icon="users"/><x-admin.stat label="Cuentas activas" :value="$activos" icon="check"/><x-admin.stat label="Administradores" :value="$administradores" icon="shield"/></div>
<form class="management-toolbar" method="get"><div class="form-group"><label for="personal-buscar">Nombre o correo</label><input id="personal-buscar" name="buscar" maxlength="100" value="{{ request('buscar') }}" placeholder="Buscar personal"></div><div class="form-group"><label for="personal-estado">Estado</label><select id="personal-estado" name="estado"><option value="">Todos</option><option value="activos" @selected(request('estado')==='activos')>Activos</option><option value="inactivos" @selected(request('estado')==='inactivos')>Inactivos</option></select></div><button class="btn btn-primary">Buscar</button><a class="btn btn-secondary" href="{{ route('superadmin.dashboard') }}">Limpiar</a></form><div class="table-wrapper">
    <table class="data-table mobile-records" data-mobile-details="1,4">
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
                <td data-label="Nombre">
                    <strong>{{ $admin->name }}</strong>
                </td>

                <td data-label="Correo">
                    {{ $admin->email }}
                </td>

                <td data-label="Rol">
                    <span class="role-badge role-{{ $admin->role }}">
                        {{ $admin->role === 'super_admin' ? 'Dueño / encargado general' : ($admin->role === 'admin' ? 'Administrador' : 'Operador') }}
                    </span>
                </td>

                <td data-label="Estado">
                    @if($admin->activo)
                    <span class="user-status-active">Activo</span>
                    @else
                    <span class="user-status-inactive">Inactivo</span>
                    @endif
                </td>

                <td data-label="Fecha registro">
                    {{ $admin->created_at ? $admin->created_at->format('d/m/Y') : '-' }}
                </td>

                <td data-label="Acciones">
                    <div class="table-actions">
                        @if($admin->role === 'operador' || ($admin->role === 'admin' && auth()->user()->role === 'super_admin'))
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
