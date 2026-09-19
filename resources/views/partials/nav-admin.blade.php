<aside class="admin-sidebar">
<div class="admin-brand"><div class="admin-logo">P</div><div><span class="admin-brand-title">Parke’o</span><span class="admin-brand-subtitle">Panel de operaciones</span></div></div>
<nav class="admin-menu" aria-label="Administración">
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Inicio</a>
@php
$grupos = [
 'Operación' => ['admin.estadias.create'=>'Registrar ingreso','admin.estadias.index'=>'Tickets y salidas','admin.placas.index'=>'Reconocer placas','admin.clientes-vehiculos.index'=>'Clientes y vehículos','admin.monitoreo.index'=>'Control de espacios'],
 'Reservas y pagos' => ['admin.reservas.index'=>'Reservas y reembolsos','admin.pagos.index'=>'Revisar pagos'],
];
$configuracion = ['admin.sensores.index'=>'Sensores IoT'];
if(auth()->user()->tieneRol('admin','super_admin')) $configuracion += ['admin.espacios.index'=>'Espacios','admin.tarifas.index'=>'Tarifas','admin.pagos.configuracion'=>'Configuración de cobros','superadmin.dashboard'=>'Personal'];
$grupos['Configuración'] = $configuracion;
@endphp
@foreach($grupos as $grupo=>$enlaces)
@php($abierto = collect(array_keys($enlaces))->contains(fn($ruta) => request()->routeIs($ruta) || (str_ends_with($ruta,'.index') && request()->routeIs(substr($ruta,0,-5).'*'))))
<details @if($abierto) open @endif><summary>{{ $grupo }} @if($grupo==='Reservas y pagos')<span class="menu-count" data-payment-count hidden></span>@endif</summary>
@foreach($enlaces as $ruta=>$nombre)<a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta) ? 'active' : '' }}">{{ $nombre }}</a>@endforeach
</details>
@if($grupo==='Reservas y pagos')<a href="{{ route('admin.reportes.index') }}" class="{{ request()->routeIs('admin.reportes.*')?'active':'' }}">Reportes</a>@endif
@endforeach
<div class="menu-footer"><a href="{{ route('public.home') }}">Ver página pública</a><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="danger">Cerrar sesión</button></form></div>
</nav></aside>
