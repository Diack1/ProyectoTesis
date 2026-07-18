@extends('layouts.auth')

@section('title', 'Iniciar sesión - Cochera Tentación')

@section('content')

<div class="auth-card">
    <div class="auth-card-header">
        <h2>Iniciar sesión</h2>
        <p>Accede a tu cuenta para reservar y gestionar tus espacios.</p>
    </div>

    @if (session('status'))
    <div class="auth-alert auth-alert-success">
        {{ session('status') }}
    </div>
    @endif

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

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="ejemplo@correo.com">
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Ingresa tu contraseña">
        </div>

        <div class="auth-row">
            <label class="checkbox-row">
                <input type="checkbox" name="remember">
                Recordarme
            </label>

            @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}">
                ¿Olvidaste tu contraseña?
            </a>
            @endif
        </div>

        <button type="submit" class="btn-auth">
            Iniciar sesión
        </button>
    </form>

    <div class="auth-bottom">
        ¿No tienes una cuenta?
        <a href="{{ route('register') }}">Regístrate aquí</a>
    </div>

    <a href="{{ route('public.home') }}" class="back-home">
        ← Volver al inicio
    </a>
</div>

@endsection