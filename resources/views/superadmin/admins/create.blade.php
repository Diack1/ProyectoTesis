@extends('layouts.admin')

@section('title', 'Nuevo miembro del personal - Parke’o')
@section('page-title', 'Nuevo miembro del personal')
@section('page-subtitle', 'Registra una cuenta de administrador o junior')

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
    <p>Usa el correo real del trabajador. Podrá ingresar con la contraseña asignada por el administrador.</p>
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
                <input type="password" autocomplete="new-password" minlength="12"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="12 caracteres, mayúsculas, números y símbolos"
                    required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input type="password" autocomplete="new-password" minlength="12"
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
                <option value="junior" @selected(old('role')==='junior')>Junior</option>
                <option value="super_admin" @selected(old('role')==='super_admin')>Administrador</option>
            </select>

            <small class="form-help">
                Junior gestiona entradas, salidas y cobros. Administrador configura el negocio, consulta informes y administra usuarios.
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
