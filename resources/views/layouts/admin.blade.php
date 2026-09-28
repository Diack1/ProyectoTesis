<!DOCTYPE html>
<html lang="es">

<head>
    @include('partials.menu-controller')
    <meta charset="UTF-8">

    <title>@yield('title', 'Panel Administrativo - Parke’o')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="/js/parkeo.js?v={{ filemtime(public_path('js/parkeo.js')) }}" defer></script>
    @vite('resources/css/admin/base.css')
    @stack('styles')
    @vite('resources/css/admin/theme.css')

</head>

<body class="atmosphere admin-refined {{ request()->routeIs('admin.dashboard') ? 'dashboard-page' : '' }}">

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
                            {{ auth()->user()->esSuperAdmin() ? 'Dueño / encargado general' : 'Recepción' }}
                        </div>
                    </div>
                </div>
            </header>

            <section class="admin-content">
                <a id="aviso-pagos" class="payment-notice" data-state="loading" href="{{ route('admin.pagos.index') }}" data-notifications="{{ route('admin.pagos.pendientes') }}" aria-live="polite"><span class="notice-icon"><x-icon name="wallet"/></span><span class="notice-copy"><strong data-notice-title>Consultando pagos</strong><small data-notice-detail>Comprobando solicitudes pendientes de revisión.</small></span><span class="notice-action">Ver pagos <span aria-hidden="true">→</span></span></a>
                @yield('content')
            </section>
        </main>
    </div>

    @stack('scripts')

</body>

</html>