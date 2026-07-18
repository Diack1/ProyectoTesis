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
                        Consulta el historial de tus reservas, revisa el estado de pago,
                        solicita reembolsos o genera una nueva reserva desde la disponibilidad pública.
                    </p>
                </div>

                <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary">
                    + Nueva reserva
                </a>
            </div>
        </div>

        @if($reservas->count() > 0)
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Espacio</th>
                        <th>Vehículo</th>
                        <th>Tarifa aplicada</th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th>Monto</th>
                        <th>Límite de pago</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($reservas as $reserva)
                    <tr>
                        <td>
                            <strong>{{ $reserva->codigo_reserva }}</strong>
                        </td>

                        <td>
                            {{ $reserva->espacio->codigo ?? '-' }}
                        </td>

                        <td>
                            {{ $reserva->tipo_vehiculo_nombre ?? '-' }}
                        </td>

                        <td>
                            <strong>{{ $reserva->tarifa_nombre ?? '-' }}</strong><br>
                            <small class="text-muted">
                                {{ $reserva->tipo_tarifa ? str_replace('_', ' ', $reserva->tipo_tarifa) : '-' }}
                            </small>
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($reserva->fecha_reserva)->format('d/m/Y') }}
                        </td>

                        <td>
                            {{ substr($reserva->hora_inicio, 0, 5) }}
                            -
                            {{ substr($reserva->hora_fin, 0, 5) }}
                        </td>

                        <td>
                            <span class="badge badge-{{ $reserva->estado }}">
                                {{ str_replace('_', ' ', $reserva->estado) }}
                            </span>
                        </td>

                        <td>
                            <strong>S/ {{ number_format($reserva->monto_total, 2) }}</strong>
                        </td>

                        <td>
                            @if($reserva->estado === 'pendiente_pago' && $reserva->expires_at)
                            {{ \Carbon\Carbon::parse($reserva->expires_at)->format('d/m/Y H:i') }}
                            @else
                            -
                            @endif
                        </td>

                        <td>
                            <div class="table-actions">
                                @if($reserva->estado === 'pendiente_pago')
                                <a href="{{ route('pagos.show', $reserva) }}" class="btn btn-primary btn-sm">
                                    Pagar
                                </a>

                                <form action="{{ route('reservas.cancelar', $reserva) }}" method="POST"
                                    onsubmit="return confirm('¿Seguro que deseas cancelar esta reserva?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Cancelar
                                    </button>
                                </form>

                                @elseif($reserva->estado === 'confirmada')
                                <form action="{{ route('reservas.solicitarReembolso', $reserva) }}" method="POST"
                                    onsubmit="return confirm('Esta reserva ya fue pagada. Se generará una solicitud de reembolso. ¿Deseas continuar?');">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        Solicitar reembolso
                                    </button>
                                </form>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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