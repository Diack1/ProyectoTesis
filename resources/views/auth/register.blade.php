@extends('layouts.auth')

@section('title', 'Registrarme - Cochera Tentación')

@section('content')

<div class="auth-card">
    <div class="auth-card-header">
        <h2>Crear cuenta</h2>
        <p>Regístrate para reservar espacios y consultar el historial de tus reservas.</p>
    </div>

    @if ($errors->any())
    <div class="auth-errors">
        <strong>Corrige los siguientes errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Nombre completo</label>
            <input id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                placeholder="Ingresa tu nombre completo">
        </div>

        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                placeholder="ejemplo@correo.com">
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repite tu contraseña">
        </div>

        <button type="submit" class="btn-auth">
            Crear cuenta
        </button>
    </form>

    <div class="auth-bottom">
        ¿Ya tienes una cuenta?
        <a href="{{ route('login') }}">Inicia sesión</a>
    </div>

    <a href="{{ route('public.home') }}" class="back-home">
        ← Volver al inicio
    </a>
</div>

@endsection