<!DOCTYPE html>
<html lang="es">

<head>
    @include('partials.menu-controller')
    <meta charset="UTF-8">

    <title>@yield('title', 'Acceso - Parke’o')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite('resources/css/auth/base.css')
    @stack('styles')
    @vite('resources/css/auth/theme.css')

<script src="/js/auth-diagonal.js?v={{ filemtime(public_path('js/auth-diagonal.js')) }}" defer></script>
</head>

<body class="atmosphere public-refined auth-diagonal {{ request()->routeIs('register') ? 'auth-register' : 'auth-login' }}">
    <header class="access-header"><x-brand :href="route('public.home')"/><a href="{{ route('public.home') }}">← Volver al inicio</a></header>
    <div class="access-stage">
    <div class="auth-shell">
        <div class="access-diagonal" aria-hidden="true"></div>
        <section class="auth-panel">
            <span class="access-symbol"><x-icon name="car"/></span>
            <span class="access-eyebrow">TU ESPACIO, ANTES DE LLEGAR</span>
            <h1>{{ request()->routeIs('register') ? 'Tu próxima parada empieza aquí.' : 'Qué bueno tenerte de vuelta.' }}</h1>
            <p>{{ request()->routeIs('register') ? 'Crea tu cuenta, verifica tu correo y elige dónde estacionar.' : 'Accede a tu cuenta y encuentra tu lugar en Parke’o.' }}</p>
            <div class="access-assurance"><x-icon name="shield"/><span>Verificación por correo<br>para proteger tu acceso</span></div>
        </section>
        <main class="auth-form-area">
            @if(request()->routeIs('login', 'register'))
            <nav class="access-tabs" aria-label="Acceso a tu cuenta">
                <a data-auth-switch href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Iniciar sesión</a>
                <a data-auth-switch href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Crear cuenta</a>
            </nav>
            @endif
            @yield('content')
        </main>
    </div>
    <p class="access-caption">Parke’o · Menos vueltas. Más tranquilidad.</p>
    </div>
    @stack('scripts')
</body>
</html>
