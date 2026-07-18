@extends('layouts.admin')

@section('title', 'Nuevo administrador - Cochera Tentación')
@section('page-title', 'Nuevo administrador')
@section('page-subtitle', 'Registra una cuenta administrativa para el sistema')

@section('content')

@if($errors->any())
<div class="errors-box">
    <strong>Corrige los siguientes errores:</strong>
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="form-card">
    <form action="{{ route('superadmin.admins.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="name">Nombre completo</label>
            <input type="text"
                name="name"
                id="name"
                class="form-control"
                value="{{ old('name') }}"
                placeholder="Ejemplo: Administrador Cochera"
                required>
        </div>

        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email"
                name="email"
                id="email"
                class="form-control"
                value="{{ old('email') }}"
                placeholder="admin@cochera.com"
                required>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Mínimo 8 caracteres"
                    required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    class="form-control"
                    placeholder="Repite la contraseña"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label for="role">Rol del usuario</label>
            <select name="role" id="role" class="form-control" required>
                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>
                    Administrador
                </option>

                <option value="super_admin" {{ old('role') === 'super_admin' ? 'selected' : '' }}>
                    Super administrador
                </option>
            </select>

            <small class="form-help">
                Usa “Administrador” para gestión operativa. Usa “Super administrador” solo para cuentas con control total.
            </small>
        </div>

        <div class="form-group">
            <label class="checkbox-pill">
                <input type="checkbox"
                    name="activo"
                    value="1"
                    {{ old('activo', true) ? 'checked' : '' }}>
                Cuenta activa
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Guardar administrador
            </button>

            <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection