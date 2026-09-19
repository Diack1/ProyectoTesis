<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>@yield('title', 'Parke’o')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/cochera-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">

    <link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">
    <script src="{{ asset('js/parkeo.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/claridad.css') }}">
    <script src="{{ asset('js/plano.js') }}" defer></script>
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
                <strong>Parke’o</strong>
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