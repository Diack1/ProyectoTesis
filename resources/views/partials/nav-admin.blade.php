<aside class="admin-sidebar">
    <div class="admin-brand">
        <div class="admin-logo">CT</div>
        <div>
            <span class="admin-brand-title">Cochera Tentación</span>
            <span class="admin-brand-subtitle">Panel administrativo</span>
        </div>
    </div>

    <nav class="admin-menu">
        <a href="{{ route('admin.dashboard') }}"
            class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            Dashboard
        </a>

        <a href="{{ route('admin.monitoreo.index') }}"
            class="{{ request()->routeIs('admin.monitoreo.*') ? 'active' : '' }}">
            Monitoreo
        </a>

        <a href="{{ route('admin.espacios.index') }}"
            class="{{ request()->routeIs('admin.espacios.*') ? 'active' : '' }}">
            Espacios
        </a>

        <a href="{{ route('admin.reservas.index') }}"
            class="{{ request()->routeIs('admin.reservas.*') ? 'active' : '' }}">
            Reservas
        </a>

        <a href="{{ route('admin.tarifas.index') }}"
            class="{{ request()->routeIs('admin.tarifas.*') ? 'active' : '' }}">
            Tarifas
        </a>

        <a href="{{ route('admin.reportes.index') }}"
            class="{{ request()->routeIs('admin.reportes.*') ? 'active' : '' }}">
            Reportes
        </a>

        @if(auth()->user()->role === 'super_admin')
        <a href="{{ route('superadmin.dashboard') }}"
            class="{{ request()->routeIs('superadmin.*') ? 'active' : '' }}">
            Super admin
        </a>
        @endif

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="danger">
                Cerrar sesión
            </button>
        </form>
    </nav>
</aside>