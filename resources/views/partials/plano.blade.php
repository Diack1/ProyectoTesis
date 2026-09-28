@php
    $administrativo = $administrativo ?? false;
@endphp
<section class="parking-widget {{ $administrativo ? 'is-list' : '' }}" data-parking @if(!$administrativo) data-refresh="{{ route('public.disponibilidad.estado') }}" @endif>
    <div class="parking-toolbar"><div><span class="eyebrow">VISTA SUPERIOR · PARKE’O</span><h2>{{ $administrativo ? 'Cada espacio, a la vista' : 'Elige tu espacio' }}</h2></div><button type="button" class="btn btn-secondary" data-map-toggle aria-pressed="{{ $administrativo ? 'true' : 'false' }}">{{ $administrativo ? 'Ver plano 2D' : 'Ver en lista' }}</button></div>
    <p class="text-muted">Pulsa un espacio para consultar su estado{{ $administrativo ? ' y gestionar la llegada o salida.' : ' y reservar si está libre.' }}</p>
    <div class="parking-legend"><span>🟢 Libre</span><span>🟠 Reservado</span><span>🔴 Ocupado</span><span>⚪ No disponible</span></div>
    <div class="parking-scroll"><div class="parking-lot">
        <div class="lot-office">Oficina</div><div class="lot-services">Servicios</div><div class="lot-lane">↑<br>PASILLO DE CIRCULACIÓN<br>↓</div><div class="lot-gate">ENTRADA / SALIDA ↑</div>
        @foreach($espacios as $espacio)
        @php
            $n = preg_match('/^E(\d{2})$/', $espacio->codigo, $m) ? (int)$m[1] : 0;
            $mapped = $n >= 1 && $n <= 30;
            if ($n <= 9) { $x=4+($n-1)*10; $y=3; $w=9; $h=12; }
            elseif ($n <= 20) { $x=80; $y=20+($n-10)*5.8; $w=17; $h=5.3; }
            elseif ($n <= 27) { $x=3; $y=39+($n-21)*5.8; $w=17; $h=5.3; }
            else { $x=25; $y=24+($n-28)*6; $w=17; $h=5.3; }
            $estado = $disponibilidadPorEspacio[$espacio->id];
        @endphp
        <button type="button" class="parking-space {{ !$mapped ? 'unmapped' : '' }}" data-space="{{ $espacio->id }}" data-state="{{ $estado['estado_visual'] }}" data-available="{{ $estado['puede_reservar'] ? '1' : '0' }}" style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%" aria-label="{{ $espacio->codigo }}: {{ $estado['estado_texto'] }}" aria-haspopup="dialog"><strong>{{ $espacio->codigo }}</strong><small data-state-label>{{ $estado['estado_texto'] }}</small></button>
        <template data-space-detail="{{ $espacio->id }}">
            <h2>{{ $espacio->codigo }}</h2><p><strong data-detail-state>{{ $estado['estado_texto'] }}</strong></p>
            <p>{{ $espacio->vehiculoTipos->pluck('nombre')->implode(' · ') ?: 'Consultar vehículos permitidos al personal' }}</p>
            @if($administrativo)<p>{{ $espacio->modo_monitoreo === 'sensor' ? 'Control por sensor' : 'Control manual por el personal' }}</p>@endif
            @if($administrativo)
                @php
                    $ticket = $espacio->estadias->first();
                @endphp
                @php
                    $reserva = $espacio->reservas->first();
                @endphp
                @if($ticket)<p>Placa: <strong>{{ $ticket->placa }}</strong><br>Ingreso: {{ $ticket->hora_ingreso->format('d/m H:i') }}<br>Tiempo estacionado: {{ (int) $ticket->hora_ingreso->diffInMinutes(now()) }} min<br>Inicio del cobro: {{ $ticket->inicio_cobro?->format('H:i') ?? 'Pendiente de detección' }}<br>{{ $ticket->reserva?->usuario?->name }}</p><a class="btn btn-primary" href="{{ route('admin.estadias.show', $ticket) }}">Cobrar y registrar salida</a>
                @elseif($reserva)<p>Reserva: {{ $reserva->codigo_reserva }}<br>Placa: {{ $reserva->placa ?? 'Sin registrar' }}<br>{{ $reserva->usuario?->name }}<br>Llegada: {{ $reserva->fecha_reserva->format('d/m') }} {{ substr($reserva->hora_inicio,0,5) }}<br>{{ str_replace('_',' ',$reserva->estado) }}</p>@if($reserva->estado === 'confirmada' && $reserva->pagos->contains('estado','aprobado'))<a class="btn btn-primary" href="{{ route('admin.estadias.create',['reserva'=>$reserva->id]) }}">Registrar llegada</a>@elseif(!$reserva->expires_at)<a class="btn btn-primary" href="{{ route('admin.pagos.index') }}">Revisar pago</a>@else<p>Esperando el pago del cliente.</p>@endif
                @elseif($espacio->estado_actual === 'libre')<a class="btn btn-primary" href="{{ route('admin.estadias.create', ['espacio_id'=>$espacio->id]) }}">Registrar vehículo aquí</a>@endif
                @if($espacio->modo_monitoreo === 'sensor')<p>{{ $espacio->sensor?->conexion_label ?? 'Sensor pendiente de instalar' }}</p>@endif
                <p><a href="{{ route('admin.monitoreo.index') }}">Abrir control de espacios</a></p>
            @else
                <a data-reserve-action class="btn btn-primary" href="{{ route('reservas.create', $espacio) }}" @if(!$estado['puede_reservar']) hidden @endif>Reservar este espacio</a>
                <p data-unavailable @if($estado['puede_reservar']) hidden @endif>Elige otro espacio libre.</p>
            @endif
        </template>
        @endforeach
        @foreach(range(1,30) as $n)
        @if(!$espacios->contains('codigo',sprintf('E%02d',$n)))
        @php
            if ($n <= 9) { $x=4+($n-1)*10; $y=3; $w=9; $h=12; }
            elseif ($n <= 20) { $x=80; $y=20+($n-10)*5.8; $w=17; $h=5.3; }
            elseif ($n <= 27) { $x=3; $y=39+($n-21)*5.8; $w=17; $h=5.3; }
            else { $x=25; $y=24+($n-28)*6; $w=17; $h=5.3; }
        @endphp
        <button class="parking-space" disabled style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%"><strong>{{ sprintf('E%02d',$n) }}</strong><small>No habilitado</small></button>
        @endif
        @endforeach
    </div></div>
    <p class="text-muted" data-map-status>{{ $administrativo ? 'Estado al abrir la página. Actualiza para consultar cambios recientes.' : 'Actualización automática cada 10 segundos.' }}</p>
    <p class="text-muted"><small>Distribución de referencia para 30 espacios; numeración propuesta para revisar en la cochera.</small></p>
    <dialog class="space-dialog"><form method="dialog"><button class="btn btn-secondary" aria-label="Cerrar detalle">Cerrar ×</button></form><div data-dialog-content></div></dialog>
</section>

@once
@push('scripts')
<script src="/js/plano.js?v={{ filemtime(public_path('js/plano.js')) }}" defer></script>
@endpush
@endonce
