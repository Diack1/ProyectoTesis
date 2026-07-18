<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>@yield('title', 'Cochera Tentación')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/cochera-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">

    @stack('styles')
</head>

<body>

    @include('partials.nav-public')

    <main>
        @yield('content')
    </main>

    <footer class="public-footer">
        <div class="container public-footer-inner">
            <div>
                <strong>Cochera Tentación</strong>
                <p>Sistema inteligente de disponibilidad y reservas de espacios.</p>
            </div>

            <div>
                <strong>Proyecto universitario</strong>
                <p>Plataforma web orientada a gestión de cochera inteligente.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')

</body>

</html>