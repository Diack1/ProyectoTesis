<nav class="public-navbar">
    <div class="container public-navbar-inner">
        <a href="{{ route('public.home') }}" class="brand">
            <div class="brand-logo">P</div>
            <div>
                <span class="brand-title">Parke’o</span>
                <span class="brand-subtitle">Cochera inteligente</span>
            </div>
        </a>

        <div class="public-menu">
            <a href="{{ route('public.home') }}"
                class="{{ request()->routeIs('public.home') ? 'active' : '' }}">
                Inicio
            </a>

            <a href="{{ route('public.disponibilidad') }}"
                class="{{ request()->routeIs('public.disponibilidad') ? 'active' : '' }}">
                Disponibilidad
            </a>

            <a href="{{ route('public.tarifas') }}"
                class="{{ request()->routeIs('public.tarifas') ? 'active' : '' }}">
                Tarifas
            </a>

            @auth
            @if(auth()->user()->role === 'user')
            <a href="{{ route('reservas.index') }}"
                class="{{ request()->routeIs('reservas.*') ? 'active' : '' }}">
                Mis reservas
            </a>
            @endif

            @if(auth()->user()->tieneRol('admin','operador','super_admin'))
            <a href="{{ route('admin.dashboard') }}">
                Panel admin
            </a>
            @endif



            <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-danger">
                    Cerrar sesión
                </button>
            </form>
            @endauth

            @guest
            <a href="{{ route('login') }}">
                Iniciar sesión
            </a>

            <a href="{{ route('register') }}" class="btn btn-primary">
                Registrarme
            </a>
            @endguest
        </div>
    </div>
</nav>
