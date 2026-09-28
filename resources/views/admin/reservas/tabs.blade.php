<nav class="operation-links" aria-label="Consultas de reservas">
<a class="btn {{ request()->routeIs('admin.reservas.index') && request('vista','hoy')==='hoy'?'btn-primary':'btn-secondary' }}" href="{{ route('admin.reservas.index',['vista'=>'hoy']) }}">Llegadas de hoy</a>
<a class="btn {{ request('vista')==='proximas'?'btn-primary':'btn-secondary' }}" href="{{ route('admin.reservas.index',['vista'=>'proximas']) }}">Próximas</a>
<a class="btn {{ request()->routeIs('admin.pagos.index')?'btn-primary':'btn-secondary' }}" href="{{ route('admin.pagos.index') }}">Pagos por revisar <span data-payment-count hidden></span></a>
<a class="btn {{ request('vista')==='historial'?'btn-primary':'btn-secondary' }}" href="{{ route('admin.reservas.index',['vista'=>'historial']) }}">Historial y reembolsos</a>
</nav>
