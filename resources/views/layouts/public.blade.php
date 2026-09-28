<!DOCTYPE html>
<html lang="es">

<head>
    @include('partials.menu-controller')
    <meta charset="UTF-8">

    <title>@yield('title', 'Parke’o')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="/js/parkeo.js?v={{ filemtime(public_path('js/parkeo.js')) }}" defer></script>
    @vite('resources/css/public/base.css')
    @stack('styles')
    @vite('resources/css/public/theme.css')

</head>

<body class="atmosphere public-refined public-hex {{ request()->routeIs('public.home') ? 'public-hex-home' : '' }}">

    @include('partials.nav-public')

    <main>
        @yield('content')
    </main>

    <footer class="public-footer"><div class="container public-footer-inner">
    <div><strong class="brand-title">Parke’o</strong><p>Tu espacio, antes de llegar.</p><a href="{{ route('public.disponibilidad') }}">Ver espacios</a> · <a href="{{ route('public.tarifas') }}">Tarifas</a></div>
    <div><strong>Conversemos</strong><p><a href="tel:+51917122915">Llamar: 917 122 915</a><br><a href="https://wa.me/51917122915" target="_blank" rel="noopener noreferrer">Escríbenos por WhatsApp ↗</a><br><a href="mailto:diackvaldizan18@gmail.com">diackvaldizan18@gmail.com</a></p></div>
    <div><strong>Horario de atención</strong><p>Lunes a domingo<br>6:30 a. m. – 11:00 p. m.</p><p class="legal-pending">Términos y condiciones · En preparación<br>Política de privacidad · En preparación</p></div>
    </div></footer>

    @stack('scripts')

</body>

</html>
