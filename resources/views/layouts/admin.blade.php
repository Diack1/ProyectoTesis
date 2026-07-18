<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>@yield('title', 'Panel Administrativo - Cochera Tentación')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/cochera-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    @stack('styles')
</head>

<body>

    <div class="admin-shell">
        @include('partials.nav-admin')

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <h1 class="admin-page-title">
                        @yield('page-title', 'Panel Administrativo')
                    </h1>

                    <p class="admin-page-subtitle">
                        @yield('page-subtitle', 'Gestión general del sistema')
                    </p>
                </div>

                <div class="admin-user-box">
                    <div class="admin-user-avatar">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>

                    <div>
                        <div class="admin-user-name">
                            {{ auth()->user()->name ?? 'Usuario' }}
                        </div>
                        <div class="admin-user-role">
                            {{ auth()->user()->role ?? 'admin' }}
                        </div>
                    </div>
                </div>
            </header>

            <section class="admin-content">
                @yield('content')
            </section>
        </main>
    </div>

    @stack('scripts')

</body>

</html>