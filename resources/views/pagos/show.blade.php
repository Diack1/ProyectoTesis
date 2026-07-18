@extends('layouts.public')

@section('title', 'Pago de reserva - Cochera Tentación')

@push('styles')
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<style>
    .payment-wrapper {
        max-width: 820px;
        margin: 0 auto;
    }

    .payment-list {
        background: #F8FAFC;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-top: 22px;
    }

    .payment-row {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--color-border);
    }

    .payment-row:last-child {
        border-bottom: none;
    }

    .payment-label {
        color: var(--color-muted);
        font-weight: 800;
    }

    .payment-value {
        color: var(--color-primary);
        font-weight: 800;
        text-align: right;
    }

    .payment-total {
        color: var(--color-success);
        font-size: 26px;
        font-weight: 900;
    }

    .payment-alert {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
        border-radius: var(--radius-md);
        padding: 16px;
        margin-top: 22px;
        font-weight: 600;
    }

    .payment-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    @media (max-width: 620px) {
        .payment-row {
            flex-direction: column;
            gap: 4px;
        }

        .payment-value {
            text-align: left;
        }
    }
</style>
@endpush

@section('content')
<section class="page-content">
    <div class="container payment-wrapper">

        <div class="page-card">

            <div class="mb-3">
                <span class="badge badge-success">Pago</span>

                <h1 class="section-title mt-1">
                    Pago de reserva
                </h1>

                <p class="section-subtitle">
                    Esta pantalla simula el proceso de pago. Más adelante puede conectarse con una pasarela real.
                </p>
            </div>

            <div class="payment-list">

                <div class="payment-row">
                    <span class="payment-label">Código de reserva</span>
                    <span class="payment-value">{{ $reserva->codigo_reserva }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Tipo de vehículo</span>
                    <span class="payment-value">{{ $reserva->tipo_vehiculo_nombre ?? '-' }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Tarifa aplicada</span>
                    <span class="payment-value">{{ $reserva->tarifa_nombre ?? '-' }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Tipo de tarifa</span>
                    <span class="payment-value">
                        {{ $reserva->tipo_tarifa ? str_replace('_', ' ', $reserva->tipo_tarifa) : '-' }}
                    </span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Espacio</span>
                    <span class="payment-value">{{ $reserva->espacio->codigo ?? '-' }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Fecha</span>
                    <span class="payment-value">{{ $reserva->fecha_reserva->format('d/m/Y') }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Horario</span>
                    <span class="payment-value">{{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Monto a pagar</span>
                    <span class="payment-value payment-total">
                        S/ {{ number_format($reserva->monto_total, 2) }}
                    </span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Tolerancia</span>
                    <span class="payment-value">{{ $reserva->tolerancia_minutos }} minutos</span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Penalidad por exceso</span>
                    <span class="payment-value">
                        S/ {{ number_format($reserva->penalidad_por_fraccion, 2) }}
                    </span>
                </div>

                <div class="payment-row">
                    <span class="payment-label">Límite de pago</span>
                    <span class="payment-value">
                        {{ $reserva->expires_at ? $reserva->expires_at->format('d/m/Y H:i') : '-' }}
                    </span>
                </div>

            </div>

            <div class="payment-alert">
                Debes realizar el pago antes del límite indicado. Si el tiempo expira, la reserva será liberada.
            </div>

            <form id="formPagoSimulado" action="{{ route('pagos.simulado', $reserva) }}" method="POST">
                @csrf

                <div class="payment-actions">
                    <button type="submit" id="btnConfirmarPago" class="btn btn-success">
                        Confirmar pago simulado
                    </button>

                    <a href="{{ route('reservas.index') }}" class="btn btn-secondary">
                        Volver a mis reservas
                    </a>
                </div>
            </form>

        </div>

    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formPago = document.getElementById('formPagoSimulado');
        const btnConfirmar = document.getElementById('btnConfirmarPago');

        if (formPago && btnConfirmar) {
            formPago.addEventListener('submit', function() {
                btnConfirmar.disabled = true;
                btnConfirmar.innerText = 'Procesando pago...';
            });
        }
    });

    window.addEventListener('pageshow', function(event) {
        const navigationEntries = performance.getEntriesByType('navigation');
        const navigationType = navigationEntries.length > 0 ? navigationEntries[0].type : null;

        if (event.persisted || navigationType === 'back_forward') {
            window.location.reload();
        }
    });
</script>

@endsection