<aside class="admin-sidebar">
<div class="admin-brand"><x-brand :href="route('admin.dashboard')" :subtitle="auth()->user()->esSuperAdmin() ? 'Administración general' : 'Recepción'"/></div>
<button type="button" class="admin-mobile-toggle" data-menu-toggle="admin-navigation" aria-controls="admin-navigation" aria-expanded="false"><x-icon name="menu"/> Menú</button>
<nav id="admin-navigation" class="admin-menu" aria-label="Administración"><button type="button" class="drawer-close" data-menu-close>Cerrar menú ×</button>
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard')?'active':'' }}"><x-icon name="grid"/>Inicio</a>
<a href="{{ route('admin.estadias.index') }}" class="{{ request()->routeIs('admin.estadias.*','admin.placas.*','admin.monitoreo.*')?'active':'' }}"><x-icon name="car"/>{{ auth()->user()->esSuperAdmin() ? 'Operación' : 'Entradas y salidas' }}</a>
<a href="{{ route('admin.reservas.index') }}" class="{{ request()->routeIs('admin.reservas.*','admin.pagos.index')?'active':'' }}"><x-icon name="clock"/>Reservas <span class="menu-count" data-payment-count hidden></span></a>
<a href="{{ route('admin.clientes-vehiculos.index') }}" class="{{ request()->routeIs('admin.clientes-vehiculos.*')?'active':'' }}"><x-icon name="users"/>Clientes</a>
@if(auth()->user()->esSuperAdmin())

<a href="{{ route('admin.reportes.index') }}" class="{{ request()->routeIs('admin.reportes.*')?'active':'' }}"><x-icon name="chart"/>Reportes</a>
<a href="{{ route('superadmin.dashboard') }}" class="{{ request()->routeIs('superadmin.*')?'active':'' }}"><x-icon name="users"/>Personal</a>
<details @if(request()->routeIs('admin.espacios.*','admin.tarifas.*','admin.pagos.configuracion*','admin.sensores.*','admin.seguridad')) open @endif><summary>Configuración</summary>
@foreach(['admin.espacios.index'=>'Espacios','admin.tarifas.index'=>'Tarifas','admin.pagos.configuracion'=>'Formas de pago','admin.sensores.index'=>'Sensores','admin.seguridad'=>'Seguridad'] as $ruta=>$nombre)
<a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta)?'active':'' }}">{{ $nombre }}</a>
@endforeach
</details>
@endif
<div class="menu-footer"><a href="{{ route('profile.edit') }}">Seguridad de mi cuenta</a><a href="{{ route('public.home') }}"><x-icon name="arrow"/>Ver página pública</a><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="danger"><x-icon name="exit"/>Cerrar sesión</button></form></div>
</nav></aside>
