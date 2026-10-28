@php
    $administrativo = $administrativo ?? false;
@endphp
<section class="parking-widget {{ $administrativo ? '' : 'parking-public-layout' }}" data-parking @if(!$administrativo) data-refresh="{{ route('public.disponibilidad.estado', [], false) }}" @endif>
    @if(!$administrativo)<script type="application/json" data-initial-quotes>@json($tarifasIniciales ?? [])</script>@endif
    <div class="parking-map-area">
    <div class="parking-toolbar"><div><span class="eyebrow">VISTA SUPERIOR · PARKE’O</span><h2>{{ $administrativo ? 'Cada espacio, a la vista' : 'Elige tu espacio' }}</h2></div></div>
    <p class="text-muted">Pulsa un espacio para consultar su estado{{ $administrativo ? ' y gestionar la llegada o salida.' : ' y reservar si está libre.' }}</p>
    <div class="parking-legend"><span>🟢 Libre</span><span>🟠 Reservado</span><span>🔴 Ocupado</span><span>⚪ No disponible</span></div>
    <div class="parking-scroll"><div class="parking-lot parking-lot-techos">
        <div class="lot-office">Oficina</div><div class="lot-services">Servicios</div><div class="lot-lane">↑<br>CIRCULACIÓN<br>↓</div><div class="lot-gate">ENTRADA / SALIDA ↑</div>
        @foreach($espacios as $espacio)
        @php
            $n = \App\Services\PlanoEspacios::numero($espacio->codigo);
            $mapped = $n >= 1 && $n <= 41;
            [$x, $y, $w, $h] = \App\Services\PlanoEspacios::posicion($n);
            $estado = $disponibilidadPorEspacio[$espacio->id];
        @endphp
        <{{ $administrativo ? 'button' : 'a' }} @if($administrativo) type="button" @else href="{{ route('reservas.create', $espacio, false) }}" role="button" @endif data-slot="{{ $n }}" class="parking-space {{ $n >= 27 && $n <= 41 ? 'space-central' : '' }} {{ !$mapped ? 'unmapped' : '' }}" data-space="{{ $espacio->id }}" data-state="{{ $estado['estado_visual'] }}" data-available="{{ $estado['puede_reservar'] ? '1' : '0' }}" style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%" aria-label="{{ $espacio->codigo }}: {{ $estado['estado_texto'] }}" aria-haspopup="dialog"><strong>{{ $espacio->codigo }}</strong><small data-state-label>{{ $estado['estado_texto'] }}</small></{{ $administrativo ? 'button' : 'a' }}>
        <template data-space-detail="{{ $espacio->id }}">
            <h2>Espacio {{ $espacio->codigo }}</h2><p><strong data-detail-state>{{ $estado['estado_texto'] }}</strong></p>
            <p>{{ $espacio->vehiculoTipos->pluck('nombre')->implode(' · ') ?: 'Consultar vehículos permitidos al personal' }}</p>
            @if($administrativo)<p>{{ $espacio->modo_monitoreo === 'sensor' ? 'Control por sensor' : 'Control manual por el personal' }}</p>@endif
            @if($administrativo)
                @php
                    $ticket = $espacio->estadias->first();
                @endphp
                @php
                    $reserva = $espacio->reservas->first();
                @endphp
                @if($asignacion = $espacio->abonoVigente()->with('abonado')->first())<p><strong>Asignado a abonado:</strong> {{ $asignacion->abonado->nombre }} · {{ $asignacion->fin->format('d/m/Y') }}<br>{{ $ticket && !$ticket->abono_id ? 'Ocupación temporal: reubicar antes de recibir al abonado.' : ($ticket ? 'Vehículo dentro' : 'Abonado ausente') }}</p><a class="btn btn-secondary" href="{{ route('admin.abonados.show',$asignacion->abonado_id) }}">Ver abonado</a>@endif
                @if($ticket)<p>Placa: <strong>{{ $ticket->placa }}</strong><br>Ingreso: {{ $ticket->hora_ingreso->format('d/m H:i') }}<br>Tiempo estacionado: {{ (int) $ticket->hora_ingreso->diffInMinutes(now()) }} min<br>Inicio del cobro: {{ $ticket->inicio_cobro?->format('H:i') ?? 'Pendiente de detección' }}<br>{{ $ticket->reserva?->usuario?->name }}</p><a class="btn btn-primary" href="{{ route('admin.estadias.show', $ticket) }}">Ver ticket / reubicar / salida</a>
                @elseif($reserva)<p>Reserva: {{ $reserva->codigo_corto }}<br>Placa: {{ $reserva->placa ?? 'Sin registrar' }}<br>{{ $reserva->usuario?->name }}<br>Llegada: {{ $reserva->fecha_reserva->format('d/m') }} {{ substr($reserva->hora_inicio,0,5) }}<br>{{ str_replace('_',' ',$reserva->estado) }}</p>@if(($reserva->pago_al_llegar && $reserva->estado === 'pendiente_pago') || ($reserva->estado === 'confirmada' && $reserva->pagos->contains('estado','aprobado')))<a class="btn btn-primary" href="{{ route('admin.estadias.create',['reserva'=>$reserva->id]) }}">Registrar llegada</a>@elseif(!$reserva->expires_at)<a class="btn btn-primary" href="{{ route('admin.pagos.index') }}">Revisar pago</a>@else<p>Esperando el pago del cliente.</p>@endif
                @elseif($espacio->estado_actual === 'libre')<a class="btn btn-primary" href="{{ route('admin.estadias.create', ['espacio_id'=>$espacio->id]) }}">Registrar vehículo aquí</a>@endif
                @if($espacio->modo_monitoreo === 'sensor')<p>{{ $espacio->sensor?->conexion_label ?? 'Sensor pendiente de instalar' }}</p>@endif
                <p><a href="{{ route('admin.monitoreo.index') }}">Abrir control de espacios</a></p>
            @else
                <div data-allowed-types="{{ $espacio->vehiculoTipos->pluck('id')->implode(',') }}" data-quote-url="{{ route('public.cotizar', $espacio, false) }}" class="space-quote" aria-live="polite"><p data-quote-message>Selecciona tu vehículo para consultar el precio.</p><strong data-quote-total></strong><small data-quote-note></small><button type="button" class="btn btn-secondary" data-quote-retry hidden>Reintentar consulta</button></div>
                <a data-reserve-action class="btn btn-primary" href="{{ route('reservas.create', $espacio) }}" @if(!$estado['puede_reservar']) hidden @endif>Reservar este espacio</a>
                <p data-unavailable @if($estado['puede_reservar']) hidden @endif>Elige otro espacio libre.</p>
            @endif
        </template>
        @endforeach
        @foreach(range(1,41) as $n)
        @if(!$espacios->contains(fn ($e) => \App\Services\PlanoEspacios::numero($e->codigo) === $n))
        @php
            [$x, $y, $w, $h] = \App\Services\PlanoEspacios::posicion($n);
        @endphp
        <button data-slot="{{ $n }}" class="parking-space {{ $n >= 27 ? 'space-central' : '' }}" disabled style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%"><strong>{{ 'T'.str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</strong><small>No habilitado</small></button>
        @endif
        @endforeach
    </div></div>
    <p class="text-muted" data-map-status>{{ $administrativo ? 'Estado al abrir la página. Actualiza para consultar cambios recientes.' : 'La disponibilidad se actualiza automáticamente.' }}</p>
    <p class="text-muted"><small>T = techo · 6 al fondo, 10 a la derecha, 7 a la izquierda y 15 centrales. El morado identifica la zona central; el texto indica su disponibilidad.</small></p>
    </div>
    <dialog class="space-dialog" aria-label="Detalle y precio del espacio"><form method="dialog"><button class="btn btn-secondary" aria-label="Cerrar detalle">Cerrar ×</button></form>
    @if(!$administrativo)
    <div class="booking-preferences">
        <div><label for="quote-vehicle">Tu vehículo</label><select id="quote-vehicle">@foreach($vehiculoTipos as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>@endforeach</select></div>
        <div><label for="quote-duration">Tiempo de estacionamiento</label>@include('partials.duration-picker', ['id'=>'quote-duration', 'name'=>'duracion_minutos', 'selected'=>60])</div>
    </div>
    @endif
    <div data-dialog-content><div class="space-empty"><x-icon name="pin"/><h2>Elige tu espacio</h2><p>Toca un espacio del plano para ver su tarifa y preparar tu reserva.</p></div></div></dialog>
</section>

@once
@push('scripts')
<script src="/js/plano.js?v={{ filemtime(public_path('js/plano.js')) }}"></script>
@endpush
@endonce
