<aside class="admin-sidebar">
<div class="admin-brand"><x-brand :href="route('admin.dashboard')" :subtitle="auth()->user()->esSuperAdmin() ? 'Administración general' : 'Recepción'"/></div>
<button type="button" class="admin-mobile-toggle" data-menu-toggle="admin-navigation" aria-controls="admin-navigation" aria-expanded="false"><x-icon name="menu"/> Menú</button>
<a class="admin-mobile-account" href="{{ route('profile.edit') }}" aria-label="Seguridad de mi cuenta">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</a>
<nav id="admin-navigation" class="admin-menu" aria-label="Administración"><button type="button" class="drawer-close" data-menu-close>Cerrar menú ×</button>
<div class="drawer-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->esSuperAdmin() ? 'Administrador' : 'Junior' }}</small></div>
@if(request()->routeIs('admin.espacios.operar'))
<a href="{{ route('admin.dashboard') }}" class="space-back"><x-icon name="arrow"/> Volver al mapa</a>
<div class="sidebar-space-meta"><span class="eyebrow">ESPACIO SELECCIONADO</span><strong>{{ $espacio->codigo }}</strong><span>{{ ucfirst(str_replace('_',' ',$espacio->estado_actual)) }}</span>@if($estadia)<small>{{ $estadia->placa }} · {{ $estadia->codigo_ticket }}</small>@endif</div>
<div class="space-actions">
@if($finalizada && $estadia)
@foreach(['comprobante'=>['wallet','Comprobante'],'ticket'=>['ticket','Ver ticket'],'historial'=>['clock','Historial']] as $key=>[$icon,$label])<a href="{{ route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>$key,'ticket'=>$estadia->id]) }}" class="{{ $vista===$key?'active':'' }}" @if($vista===$key) aria-current="page" @endif><x-icon :name="$icon"/>{{ $label }}</a>@endforeach
@elseif($estadia)
@foreach(['cobrar'=>['wallet','Cobrar'],'ticket'=>['ticket','Ver ticket'],'editar'=>['edit','Editar registro'],'mover'=>['arrow','Mover vehículo'],'historial'=>['clock','Historial']] as $key=>[$icon,$label])
<a href="{{ route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>$key]) }}" class="{{ $vista===$key?'active':'' }}" @if($vista===$key) aria-current="page" @endif><x-icon :name="$icon"/>{{ $label }}</a>
@endforeach
@elseif($reserva)<a href="{{ route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'reserva']) }}" class="active"><x-icon name="clock"/>Ver reserva</a>
@elseif($vista==='ingreso')<a href="{{ route('admin.espacios.operar',$espacio) }}" class="active"><x-icon name="car"/>Registrar ingreso</a>
@else<a href="{{ route('admin.espacios.operar',$espacio) }}" class="active"><x-icon name="pin"/>Estado del espacio</a>@endif
</div>
@else
<div class="sidebar-quick-actions"><a href="{{ route('admin.estadias.create') }}"><x-icon name="car"/>Registrar entrada</a><a href="{{ route('admin.estadias.escanear') }}"><x-icon name="exit"/>Registrar salida</a></div>
<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard')?'active':'' }}"><x-icon name="grid"/>Inicio</a>
<details @if(request()->routeIs('admin.resumen.*')) open @endif><summary><x-icon name="chart"/> Actividad</summary><a href="{{ route('admin.resumen.index') }}" class="{{ request()->routeIs('admin.resumen.*')?'active':'' }}">Resumen del día</a></details>
<a href="{{ route('admin.estadias.index') }}" class="{{ request()->routeIs('admin.estadias.*','admin.placas.*','admin.monitoreo.*')?'active':'' }}"><x-icon name="car"/>{{ auth()->user()->esSuperAdmin() ? 'Operación' : 'Entradas y salidas' }}</a>
<a href="{{ route('admin.reservas.index') }}" class="{{ request()->routeIs('admin.reservas.*','admin.pagos.index')?'active':'' }}"><x-icon name="clock"/>Reservas <span class="menu-count" data-payment-count hidden></span></a>
<a href="{{ route('admin.abonados.index') }}" class="{{ request()->routeIs('admin.abonados.*')?'active':'' }}"><x-icon name="users"/>Abonados</a>
<a href="{{ route('admin.caja.index') }}" class="{{ request()->routeIs('admin.caja.*')?'active':'' }}"><x-icon name="wallet"/>Caja</a>
@if(auth()->user()->esSuperAdmin())<a href="{{ route('admin.impresoras.index') }}" class="{{ request()->routeIs('admin.impresoras.*')?'active':'' }}"><x-icon name="print"/>Impresoras</a>@endif
<a href="{{ route('admin.clientes-vehiculos.index') }}" class="{{ request()->routeIs('admin.clientes-vehiculos.*')?'active':'' }}"><x-icon name="users"/>Clientes</a>
@if(auth()->user()->esSuperAdmin())

<a href="{{ route('admin.informes.index') }}" class="{{ request()->routeIs('admin.informes.*','admin.reportes.*')?'active':'' }}"><x-icon name="chart"/>Informes</a>
<a href="{{ route('superadmin.dashboard') }}" class="{{ request()->routeIs('superadmin.*')?'active':'' }}"><x-icon name="users"/>Usuarios</a>
<details @if(request()->routeIs('admin.espacios.*','admin.tarifas.*','admin.pagos.configuracion*','admin.sensores.*','admin.seguridad')) open @endif><summary>Configuración</summary>
@foreach(['admin.espacios.index'=>'Espacios','admin.tarifas.index'=>'Tarifas','admin.pagos.configuracion'=>'Formas de pago','admin.sensores.index'=>'Sensores','admin.seguridad'=>'Seguridad'] as $ruta=>$nombre)
<a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta)?'active':'' }}">{{ $nombre }}</a>
@endforeach
</details>
@endif
@endif
<div class="menu-footer"><a href="{{ route('profile.edit') }}">Seguridad de mi cuenta</a><a href="{{ route('public.home') }}"><x-icon name="arrow"/>Ver página pública</a><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="danger"><x-icon name="exit"/>Cerrar sesión</button></form></div>
</nav></aside>
