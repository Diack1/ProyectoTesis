@extends('layouts.public')

@section('title', 'Confirmar reserva - Parke’o')

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
        font-weight: 600;
    }

    .reservation-value {
        color: var(--color-primary);
        font-weight: 600;
        text-align: left;
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
@php($resumen = \App\Services\TarifaResumen::presentar($calculo))
<section class="page-content">
    <div class="container reservation-wrapper reservation-review">

        <div class="page-card">

            <div class="mb-3">
                @include('partials.booking-steps',['step'=>2])
                <h1 class="section-title mt-1">Confirmar reserva</h1>
                <p class="section-subtitle">
                    Revisa tus datos y el total. Después podrás enviar tu pago por Yape o Plin.
                </p>
            </div>

            <div class="arrival-policy"><h3>Reserva para llegar ahora</h3><p>Tras aprobar tu pago, tendrás 15 minutos para llegar. Consulta la hora límite en Mis reservas.</p></div><div class="reservation-list">
                <div class="reservation-row">
                    <span class="reservation-label">Placa del vehículo</span>
                    <span class="reservation-value">{{ $placa }}</span>
                </div>

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
                    <span class="reservation-value">{{ $resumen['precio_unitario'] }}</span>
                </div>



                <div class="reservation-row">
                    <span class="reservation-label">Llegada prevista</span>
                    <span class="reservation-value">15 minutos desde la aprobación del pago</span>
                </div>



                <div class="reservation-row">
                    <span class="reservation-label">Duración</span>
                    <span class="reservation-value">{{ \App\Services\TarifaResumen::tiempo($duracionMinutos) }}</span>
                </div>


                <div class="reservation-row">
                    <span class="reservation-label">Monto total</span>
                    <span class="reservation-value reservation-total">
                        S/ {{ number_format($calculo['monto_total'], 2) }}
                    </span>
                </div>

                <div class="reservation-row reservation-explanation">
                    <span class="reservation-label">Cómo se calcula</span>
                    <span class="reservation-value">{{ $resumen['detalle_precio'] }}</span>
                </div>

                <div class="reservation-row reservation-explanation">
                    <span class="reservation-label">Si te quedas más tiempo</span>
                    <span class="reservation-value">{{ $resumen['exceso'] }}</span>
                </div>

            </div>

            <div class="alert alert-info">Tu tiempo de estacionamiento comienza al registrar tu ingreso. Una vez aprobado el pago, tienes 15 minutos para llegar. Si no llegas dentro de ese plazo, el espacio se libera y el personal revisará si corresponde un reembolso.</div><div class="reservation-alert">
                Al confirmar, el espacio quedará reservado temporalmente durante el plazo indicado en la pantalla de pago.
            </div>

            <form id="formConfirmarReserva" action="{{ route('reservas.store', $espacio) }}" method="POST" autocomplete="off">
                @csrf

                <input type="hidden" name="vehiculo_tipo_id" value="{{ $vehiculoTipo->id }}">
                <input type="hidden" name="placa" value="{{ $placa }}">
                <input type="hidden" name="fecha_reserva" value="{{ $fechaReserva }}">
                <input type="hidden" name="hora_inicio" value="{{ $horaInicio }}">
                <input type="hidden" name="duracion_minutos" value="{{ $duracionMinutos }}">

                <div class="reservation-actions">
                    <button type="submit" id="btnConfirmarReserva" class="btn btn-primary">
                        Confirmar y continuar al pago
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
                btnConfirmar.innerText = 'Procesando...';
            });
        }
    });

    window.addEventListener('pageshow', function() {
        const button = document.getElementById('btnConfirmarReserva');
        if (button) { button.disabled = false; button.textContent = 'Confirmar y continuar al pago'; }
    });
</script>

@endsection
