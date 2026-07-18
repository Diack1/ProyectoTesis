@extends('layouts.public')

@section('title', 'Confirmar reserva - Cochera Tentación')

@push('styles')
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<style>
    .reservation-wrapper {
        max-width: 820px;
        margin: 0 auto;
    }

    .reservation-list {
        background: #F8FAFC;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-top: 22px;
    }

    .reservation-row {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--color-border);
    }

    .reservation-row:last-child {
        border-bottom: none;
    }

    .reservation-label {
        color: var(--color-muted);
        font-weight: 800;
    }

    .reservation-value {
        color: var(--color-primary);
        font-weight: 800;
        text-align: right;
    }

    .reservation-total {
        color: var(--color-success);
        font-size: 24px;
    }

    .reservation-alert {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
        border-radius: var(--radius-md);
        padding: 16px;
        margin-top: 22px;
        font-weight: 600;
    }

    .reservation-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    @media (max-width: 620px) {
        .reservation-row {
            flex-direction: column;
            gap: 4px;
        }

        .reservation-value {
            text-align: left;
        }
    }
</style>
@endpush

@section('content')
<section class="page-content">
    <div class="container reservation-wrapper">

        <div class="page-card">

            <div class="mb-3">
                <span class="badge badge-warning">Confirmación</span>
                <h1 class="section-title mt-1">Confirmar reserva</h1>
                <p class="section-subtitle">
                    Revisa los datos antes de continuar. En el siguiente paso se generará una reserva
                    con estado <strong>pendiente de pago</strong>.
                </p>
            </div>

            <div class="reservation-list">

                <div class="reservation-row">
                    <span class="reservation-label">Espacio seleccionado</span>
                    <span class="reservation-value">{{ $espacio->codigo }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tipo de vehículo</span>
                    <span class="reservation-value">{{ $vehiculoTipo->nombre }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tarifa aplicada</span>
                    <span class="reservation-value">{{ $calculo['tarifa']->nombre }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tipo de tarifa</span>
                    <span class="reservation-value">{{ $calculo['tarifa']->tipo_tarifa }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Fecha</span>
                    <span class="reservation-value">{{ $fechaHoraInicio->format('d/m/Y') }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Hora de ingreso</span>
                    <span class="reservation-value">{{ $fechaHoraInicio->format('H:i') }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Hora de salida</span>
                    <span class="reservation-value">{{ $fechaHoraFin->format('H:i') }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Duración</span>
                    <span class="reservation-value">{{ $duracionMinutos / 60 }} hora(s)</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tarifa por hora</span>
                    <span class="reservation-value">S/ {{ number_format($calculo['tarifa_hora'], 2) }}</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Monto total</span>
                    <span class="reservation-value reservation-total">
                        S/ {{ number_format($calculo['monto_total'], 2) }}
                    </span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tiempo mínimo de cobro</span>
                    <span class="reservation-value">{{ $calculo['minutos_cobro'] }} minutos</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Tolerancia</span>
                    <span class="reservation-value">{{ $calculo['tolerancia_minutos'] }} minutos</span>
                </div>

                <div class="reservation-row">
                    <span class="reservation-label">Penalidad por exceso</span>
                    <span class="reservation-value">S/ {{ number_format($calculo['penalidad_por_fraccion'], 2) }}</span>
                </div>

            </div>

            <div class="reservation-alert">
                Al confirmar, el espacio quedará reservado temporalmente por 10 minutos mientras realizas el pago.
            </div>

            <form id="formConfirmarReserva" action="{{ route('reservas.store', $espacio) }}" method="POST" autocomplete="off">
                @csrf

                <input type="hidden" name="vehiculo_tipo_id" value="{{ $vehiculoTipo->id }}">
                <input type="hidden" name="fecha_reserva" value="{{ $fechaReserva }}">
                <input type="hidden" name="hora_inicio" value="{{ $horaInicio }}">
                <input type="hidden" name="duracion_minutos" value="{{ $duracionMinutos }}">

                <div class="reservation-actions">
                    <button type="submit" id="btnConfirmarReserva" class="btn btn-primary">
                        Confirmar y generar reserva
                    </button>

                    <a href="{{ route('reservas.create', $espacio) }}" class="btn btn-secondary">
                        Corregir datos
                    </a>
                </div>
            </form>

        </div>

    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formReserva = document.getElementById('formConfirmarReserva');
        const btnConfirmar = document.getElementById('btnConfirmarReserva');

        if (formReserva && btnConfirmar) {
            formReserva.addEventListener('submit', function() {
                btnConfirmar.disabled = true;
                btnConfirmar.innerText = 'Generando reserva...';
            });
        }
    });

    window.addEventListener('pageshow', function(event) {
        const navigationEntries = performance.getEntriesByType('navigation');
        const navigationType = navigationEntries.length > 0 ? navigationEntries[0].type : null;

        if (event.persisted || navigationType === 'back_forward') {
            window.location.replace("{{ route('reservas.index') }}");
        }
    });
</script>

@endsection