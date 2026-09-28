@extends('layouts.public')

@section('title', 'Mis reservas')

@section('content')

<section class="page-content">
    <div class="container">

        @if(session('success'))
        <div class="alert-box alert-success">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert-box alert-error">
            {{ session('error') }}
        </div>
        @endif

        <div class="page-card">
            <div class="flex-between flex-wrap">
                <div>
                    <span class="badge badge-info">Portal de usuario</span>

                    <h1 class="section-title" style="margin-top:14px;">
                        Mis reservas
                    </h1>

                    <p class="section-subtitle">
                        Consulta tu pago y el plazo de llegada. Tus reservas activas aparecen primero.
                    </p>
                </div>

                <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary">
                    + Nueva reserva
                </a>
            </div>
        </div>

        @if($reservas->count() > 0)
        <div class="reservation-cards">
        @foreach($reservas as $reserva)
        <article class="reservation-item">
        <div><span class="badge badge-{{ $reserva->estado }}">{{ $reserva->estado === 'pendiente_pago' && !$reserva->expires_at ? 'Pago en revisión' : ucfirst(str_replace('_',' ',$reserva->estado)) }}</span>
        <h2>{{ $reserva->placa ?? 'Sin placa registrada' }} · Espacio {{ $reserva->espacio->codigo ?? '-' }}</h2>
        <details class="reservation-more"><summary>Ver detalles</summary><p class="reservation-code">{{ $reserva->codigo_reserva }}</p>
        <dl><div><dt>Fecha de llegada</dt><dd>{{ $reserva->fecha_reserva->format('d/m/Y') }}</dd></div>
        <div><dt>Llegada prevista</dt><dd>{{ $reserva->reserva_inmediata ? ($reserva->pagado_at ? 'Antes de '.$reserva->limite_llegada->format('H:i') : '15 min desde aprobación') : substr($reserva->hora_inicio,0,5) }}</dd></div>
        <div><dt>Tiempo contratado</dt><dd>{{ $reserva->duracion_minutos }} min</dd></div>
        <div><dt>Vehículo</dt><dd>{{ $reserva->tipo_vehiculo_nombre ?? '-' }}</dd></div>
        <div><dt>Tarifa aplicada</dt><dd>{{ $reserva->tarifa_nombre ?? '-' }}</dd></div></dl></details>
        </div><div class="reservation-payment"><div class="text-muted">Monto de la reserva</div><div class="amount">S/ {{ number_format($reserva->monto_total,2) }}</div>
        @if($reserva->estado === 'pendiente_pago' && $reserva->expires_at)<p class="payment-deadline"><strong>Paga antes del</strong><br>{{ $reserva->expires_at->format('d/m/Y · H:i') }}</p>
        @elseif($reserva->estado === 'pendiente_pago')<p class="payment-deadline">Recibimos tu comprobante. El personal está revisando el pago.</p>@endif
                            @if($reserva->inasistencia_at)<p>La tolerancia venció y el espacio fue liberado. El pago quedó para revisión y posible reembolso manual.</p>
                                @elseif($reserva->estadia)<p>{{ $reserva->estadia->estado_label }} · Inicio: {{ $reserva->estadia->inicio_cobro?->format('H:i') ?? 'Esperando sensor' }}</p>
                                @elseif($reserva->estado === 'confirmada')<p>Te esperamos hasta {{ $reserva->limite_llegada->format('H:i') }}.</p>
                                @endif
                                <div class="reservation-buttons">
                                @if($reserva->estado === 'pendiente_pago')
                                <a href="{{ route('pagos.show', $reserva) }}" class="btn btn-primary btn-sm">
                                    Ver pago
                                </a>

                                @if($reserva->expires_at)<form action="{{ route('reservas.cancelar', $reserva) }}" method="POST"
                                    onsubmit="return confirm('¿Seguro que deseas cancelar esta reserva?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Cancelar
                                    </button>
                                </form>

                                @endif
                                @elseif($reserva->estado === 'confirmada' && !$reserva->estadia)
                                <form action="{{ route('reservas.solicitarReembolso', $reserva) }}" method="POST"
                                    onsubmit="return confirm('Esta reserva ya fue pagada. Se generará una solicitud de reembolso. ¿Deseas continuar?');">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        Solicitar reembolso
                                    </button>
                                </form>
                                @else
                                <span class="text-muted">Sin acciones pendientes</span>
                                @endif
                            </div>
        </div></article>
        @endforeach
        </div>

        <div style="margin-top:18px;">
            {{ $reservas->links() }}
        </div>
        @else
        <div class="page-card">
            <h2 style="margin-top:0;">Todavía no tienes reservas registradas</h2>

            <p class="section-subtitle">
                Puedes revisar la disponibilidad actual de espacios y seleccionar una tarjeta libre
                para iniciar una nueva reserva.
            </p>

            <div class="mt-2">
                <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary">
                    Ver disponibilidad
                </a>
            </div>
        </div>
        @endif

    </div>
</section>

@endsection