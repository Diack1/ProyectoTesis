@extends('layouts.public')

@section('title', 'Nueva reserva - Parke’o')



@push('styles')
<style>
    .reservation-wrapper {
        max-width: 820px;
        margin: 0 auto;
    }

    .reservation-selected {
        background: #ECFDF5;
        color: #065F46;
        border: 1px solid #BBF7D0;
        padding: 22px;
        border-radius: var(--radius-lg);
        margin-bottom: 24px;
    }

    .reservation-selected h2 {
        margin: 0 0 10px;
        font-size: 22px;
        color: #065F46;
    }

    .reservation-selected p {
        margin: 6px 0;
    }

    .reservation-summary {
        background: #F8FAFC;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 18px;
        margin-top: 22px;
        color: var(--color-text);
    }

    .reservation-summary p {
        margin: 0 0 10px;
    }

    .reservation-summary p:last-child {
        margin-bottom: 0;
    }

    .reservation-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
    }
</style>
@endpush

@section('content')
<section class="page-content">
    <div class="container reservation-wrapper">

        <div class="page-card">

            <div class="mb-3">
                @include('partials.booking-steps',['step'=>1])
                <h1 class="section-title mt-1">Nueva reserva</h1>
                <p class="section-subtitle">
                    Completa los datos para reservar tu espacio de estacionamiento.
                </p>
            </div>

            @if($errors->any())
            <div class="alert-box alert-error">
                <strong>Corrige los siguientes errores:</strong>
                <ul style="margin: 8px 0 0;">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(session('error'))
            <div class="alert-box alert-error">
                {{ session('error') }}
            </div>
            @endif

            <div class="reservation-selected"><x-icon name="car"/><div><h2>Tu espacio: {{ $espacio->codigo }}</h2><a href="{{ route('public.disponibilidad') }}">Elegir otro espacio</a></div></div>
            @php
            $fechaMinima = now('America/Lima')->format('Y-m-d');
            $fechaMaxima = now('America/Lima')->addDay()->format('Y-m-d');

            // Hora mínima sugerida para reservas de hoy: 15 minutos después de la hora actual
            $horaMinimaHoy = now('America/Lima')->addMinutes(15)->format('H:i');
            @endphp

            <p class="booking-note">Paga por Yape o Plin y revisa la aprobación en tu cuenta. Reservamos tu espacio para una llegada inmediata.</p>
<form id="formNuevaReserva" data-server-now="{{ now()->getTimestampMs() }}" action="{{ route('reservas.confirmar', $espacio) }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="vehiculo_tipo_id">Tipo de vehículo</label>
                    <select name="vehiculo_tipo_id" id="vehiculo_tipo_id" required>
                        <option value="">Seleccione tipo de vehículo</option>
                        @foreach($vehiculoTipos as $tipo)
                        <option value="{{ $tipo->id }}" {{ old('vehiculo_tipo_id') == $tipo->id ? 'selected' : '' }}>
                            {{ $tipo->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="placa">Placa del vehículo</label>
                    <input id="placa" name="placa" value="{{ old('placa') }}" maxlength="20" placeholder="ABC-123" required autocomplete="off" aria-describedby="placa-ayuda">
                    <small id="placa-ayuda">Usa la placa del vehículo con el que llegarás.</small>
                    @error('placa')<p class="text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="arrival-policy"><span class="badge badge-info">Reserva inmediata</span><h3>Tienes 15 minutos para llegar</h3><p>El plazo empieza cuando recepción apruebe tu pago. Verás la hora límite en Mis reservas.</p><input type="hidden" id="hora_inicio" value="{{ now('America/Lima')->format('H:i') }}"></div>
                <div class="form-group">
                    <label for="duracion_minutos">Duración</label>
                    <select name="duracion_minutos" id="duracion_minutos" required>
                        <option value="60" {{ old('duracion_minutos') == 60 ? 'selected' : '' }}>1 hora</option>
                        <option value="120" {{ old('duracion_minutos') == 120 ? 'selected' : '' }}>2 horas</option>
                        <option value="180" {{ old('duracion_minutos') == 180 ? 'selected' : '' }}>3 horas</option>
                        <option value="240" {{ old('duracion_minutos') == 240 ? 'selected' : '' }}>4 horas</option>
                    </select>
                </div>

                <aside class="reservation-summary" id="resumenTarifaReserva"><h2>Tu reserva de un vistazo</h2><p><strong>Espacio:</strong> {{ $espacio->codigo }}</p><p><strong>Vehículo:</strong> <span data-booking-vehicle>Por elegir</span></p><p><strong>Placa:</strong> <span data-booking-plate>Por completar</span></p><p><strong>Llegada:</strong> <span>Dentro de los 15 minutos posteriores a la aprobación del pago</span></p>
                    <p><strong>Tarifa:</strong> <span data-role="tarifa-nombre">Selecciona un tipo de vehiculo.</span></p>
                    <p><strong>Duración:</strong> <span data-role="tarifa-duracion">-</span></p>
                    <p><strong>Monto estimado:</strong> <span data-role="tarifa-monto">-</span></p>
                    <p><strong>Tolerancia de exceso:</strong> <span data-role="tarifa-tolerancia">-</span></p>
                    <p class="booking-note">
                        Verás el total confirmado antes de pagar.
                    </p>
                <p><strong>Plazo para enviar el pago:</strong> {{ \App\Models\ConfiguracionPago::actual()->minutos_pago }} min desde la confirmación.</p><p><strong>Tolerancia de llegada:</strong> 15 min desde la aprobación del pago.</p><details><summary>Sobre tu llegada y cancelación</summary><p>El tiempo comienza cuando te estacionas en un espacio con sensor o al emitir el ticket en un espacio manual. Si no llegas dentro de la tolerancia, se libera el espacio y tu pago queda para revisión y posible reembolso manual.</p></details></aside>
                <div class="reservation-actions">
                    <button type="submit" id="btnNuevaReserva" class="btn btn-primary">
                        Continuar a confirmación
                    </button>

                    <a href="{{ route('public.disponibilidad') }}" class="btn btn-secondary">
                        Volver
                    </a>
                </div>
            </form>

        </div>

    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const horaInput = document.getElementById('hora_inicio');
        const formNuevaReserva = document.getElementById('formNuevaReserva');
        const btnNuevaReserva = document.getElementById('btnNuevaReserva');
        const vehiculoTipoInput = document.getElementById('vehiculo_tipo_id');
        const duracionInput = document.getElementById('duracion_minutos');
        const resumenTarifa = document.getElementById('resumenTarifaReserva');
        const tarifasPorTipo = @json($tarifasFrontend ?? []);

        function horaDentroDeRango(horaActual, horaInicio, horaFin) {
            if (!horaInicio || !horaFin) {
                return false;
            }

            if (horaInicio <= horaFin) {
                return horaActual >= horaInicio && horaActual <= horaFin;
            }

            return horaActual >= horaInicio || horaActual <= horaFin;
        }

        function tarifaAplicable(tarifas, hora, duracion) {
            const nocturna = tarifas.find(function(tarifa) {
                return tarifa.tipo_tarifa === 'nocturna'
                    && horaDentroDeRango(hora + ':00', tarifa.hora_inicio, tarifa.hora_fin);
            });

            if (nocturna) {
                return nocturna;
            }

            if (duracion >= 1440) {
                const diaria = tarifas.find(function(tarifa) {
                    return tarifa.tipo_tarifa === 'diaria';
                });

                if (diaria) {
                    return diaria;
                }
            }

            return tarifas.find(function(tarifa) {
                return tarifa.tipo_tarifa === 'por_hora';
            }) || tarifas.find(function(tarifa) {
                return tarifa.tipo_tarifa === 'fraccion';
            }) || tarifas[0];
        }

        function calcularMonto(tarifa, duracion) {
            const minutosCobro = Math.max(duracion, Number(tarifa.tiempo_minimo_minutos || 60));

            if (tarifa.tipo_tarifa === 'diaria') {
                return Number(tarifa.monto_base || 0) > 0
                    ? Number(tarifa.monto_base)
                    : Number(tarifa.monto_por_hora || 0) * 24;
            }

            if (tarifa.tipo_tarifa === 'nocturna' && Number(tarifa.monto_base || 0) > 0) {
                return Number(tarifa.monto_base);
            }

            if (tarifa.tipo_tarifa === 'fraccion' && tarifa.monto_por_fraccion && tarifa.minutos_fraccion) {
                return Math.ceil(minutosCobro / Number(tarifa.minutos_fraccion)) * Number(tarifa.monto_por_fraccion);
            }

            const horas = Math.floor(minutosCobro / 60) || 1;
            const minutosRestantes = minutosCobro % 60;
            let total = horas * Number(tarifa.monto_por_hora || 0);

            if (minutosRestantes > 0) {
                total += tarifa.monto_por_fraccion && tarifa.minutos_fraccion
                    ? Math.ceil(minutosRestantes / Number(tarifa.minutos_fraccion)) * Number(tarifa.monto_por_fraccion)
                    : Number(tarifa.monto_por_hora || 0);
            }

            return total;
        }

        function actualizarResumenTarifa() {
            if (!resumenTarifa || !vehiculoTipoInput || !duracionInput) {
                return;
            }

            const tipoId = vehiculoTipoInput.value;
            const tarifas = tarifasPorTipo[tipoId] || [];
            const duracion = Number(duracionInput.value || 0);
            const tarifa = tarifaAplicable(tarifas, horaInput.value || '00:00', duracion);

            const nombre = resumenTarifa.querySelector('[data-role="tarifa-nombre"]');
            const duracionTexto = resumenTarifa.querySelector('[data-role="tarifa-duracion"]');
            const monto = resumenTarifa.querySelector('[data-role="tarifa-monto"]');
            const tolerancia = resumenTarifa.querySelector('[data-role="tarifa-tolerancia"]');

            if (!tarifa) {
                nombre.textContent = 'Selecciona un tipo de vehiculo.';
                duracionTexto.textContent = '-';
                monto.textContent = '-';
                tolerancia.textContent = '-';
                return;
            }

            nombre.textContent = tarifa.nombre;
            duracionTexto.textContent = (duracion / 60) + ' hora(s)';
            monto.textContent = 'S/ ' + calcularMonto(tarifa, duracion).toFixed(2);
            tolerancia.textContent = Number(tarifa.tolerancia_minutos || 0) + ' minutos';
        }

        [vehiculoTipoInput, duracionInput, horaInput].forEach(function(input) {
            if (input) {
                input.addEventListener('change', actualizarResumenTarifa);
                input.addEventListener('input', actualizarResumenTarifa);
            }
        });

        actualizarResumenTarifa();

        if (formNuevaReserva && btnNuevaReserva) {
            formNuevaReserva.addEventListener('submit', function() {
                btnNuevaReserva.disabled = true;
                btnNuevaReserva.innerText = 'Procesando...';
            });
        }
    });
</script>

@endsection

@push('scripts')
<script src="/js/reserva-publica.js?v={{ filemtime(public_path('js/reserva-publica.js')) }}" defer></script>
@endpush
