@php($estado=$espacio->estado_actual)
@php($reservable=$estado==='libre' && $espacio->vehiculoTipos->count())
@if($reservable)<a class="whole-space-link" href="{{ route('reservas.create',$espacio) }}" aria-label="Reservar espacio {{ $espacio->codigo }}">@endif
<article class="space-card state-{{ $estado }}" data-space-type="{{ $espacio->vehiculoTipos->pluck('codigo')->implode(' ') }}" data-space-status="{{ $estado }}">
<div class="space-card-top"><span class="space-code">{{ $espacio->codigo }}</span><span class="badge badge-{{ $estado }}"><span class="status-dot"></span>{{ ucfirst($estado) }}</span></div>
<div class="space-car"><x-icon name="car"/></div><div class="space-card-meta"><span>{{ $espacio->vehiculoTipos->pluck('nombre')->implode(' · ') ?: 'Consultar al personal' }}</span><span class="muted">Parke’o</span></div>
@if($estado==='libre' && $espacio->vehiculoTipos->count())<span class="space-action">Reservar espacio <x-icon name="arrow"/></span>@else<span class="space-action unavailable">No disponible para reservar</span>@endif
</article>@if($reservable)</a>@endif
