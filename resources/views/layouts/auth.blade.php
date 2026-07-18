<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>@yield('title', 'Acceso - Cochera Tentación')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/cochera-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">

    @stack('styles')
</head>

<body>

    <div class="auth-shell">
        <section class="auth-panel">
            <a href="{{ route('public.home') }}" class="auth-brand">
                <div class="auth-logo">CT</div>
                <div>
                    <span class="auth-brand-title">Cochera Tentación</span>
                    <span class="auth-brand-subtitle">Cochera inteligente</span>
                </div>
            </a>

            <div class="auth-panel-content">
                <h1>Reserva tu espacio de forma rápida y segura</h1>
                <p>
                    Consulta disponibilidad, selecciona un espacio libre y gestiona tus reservas
                    desde una plataforma moderna y confiable.
                </p>

                <div class="auth-features">
                    <div class="auth-feature">
                        <strong>Disponibilidad en tiempo real</strong>
                        <span>Consulta espacios libres, ocupados, reservados o en mantenimiento.</span>
                    </div>

                    <div class="auth-feature">
                        <strong>Reservas con control de pago</strong>
                        <span>Genera reservas pendientes, confirma pagos y revisa tu historial.</span>
                    </div>

                    <div class="auth-feature">
                        <strong>Gestión inteligente</strong>
                        <span>Plataforma preparada para integración con sensores IoT.</span>
                    </div>
                </div>
            </div>

            <div class="auth-panel-footer">
                Proyecto universitario orientado a gestión inteligente de disponibilidad y reservas.
            </div>
        </section>

        <main class="auth-form-area">
            @yield('content')
        </main>
    </div>
    @stack('scripts')

</body>

</html>