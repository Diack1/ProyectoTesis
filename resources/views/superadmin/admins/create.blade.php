@extends('layouts.admin')

@section('title', 'Nuevo miembro del personal - Parke’o')
@section('page-title', 'Nuevo miembro del personal')
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
                <option value="admin">Recepción</option>
            </select>

            <small class="form-help">
                El operador monitorea espacios y revisa pagos. El administrador también configura el negocio.
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
                Guardar cuenta
            </button>

            <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection