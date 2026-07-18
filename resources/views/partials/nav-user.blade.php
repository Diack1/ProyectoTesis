<nav class="public-header">
    <div class="public-header-inner">
        <a href="{{ route('public.home') }}" class="brand">
            <div class="brand-logo">CT</div>
            <div class="brand-text">
                <span class="brand-title">Cochera Tentación</span>
                <span class="brand-subtitle">Portal de usuario</span>
            </div>
        </a>

        <div class="public-nav">
            <a href="{{ route('public.home') }}">Inicio</a>
            <a href="{{ route('public.disponibilidad') }}">Disponibilidad</a>
            <a href="{{ route('public.tarifas') }}">Tarifas</a>
            <a href="{{ route('reservas.index') }}">Mis reservas</a>

            <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-danger">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>