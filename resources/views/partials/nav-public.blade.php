<nav class="public-navbar">
    <div class="container public-navbar-inner">
        <x-brand :href="route('public.home')"/>

        <button type="button" class="public-menu-toggle" data-menu-toggle="public-navigation" aria-controls="public-navigation" aria-expanded="false"><x-icon name="menu"/> Menú</button><div id="public-navigation" class="public-menu"><button type="button" class="drawer-close" data-menu-close>Cerrar menú ×</button>
            <a href="{{ route('public.home') }}"
                class="{{ request()->routeIs('public.home') ? 'active' : '' }}">
                Inicio
            </a>

            <a href="{{ route('public.disponibilidad') }}"
                class="{{ request()->routeIs('public.disponibilidad') ? 'active' : '' }}">
                Disponibilidad
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
                Administración
            </a>
            @endif



            <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-secondary">
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
