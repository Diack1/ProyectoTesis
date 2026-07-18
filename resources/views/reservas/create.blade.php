@extends('layouts.public')

@section('title', 'Nueva reserva - Cochera Tentación')

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
                <span class="badge badge-info">Reserva</span>
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

            <div class="reservation-selected">
                <h2>Espacio seleccionado: {{ $espacio->codigo }}</h2>
                <p>{{ $espacio->descripcion }}</p>
                <p>Estado actual: <strong>{{ $espacio->estado_actual }}</strong></p>
            </div>

            @php
            $fechaMinima = now('America/Lima')->format('Y-m-d');
            $fechaMaxima = now('America/Lima')->addDay()->format('Y-m-d');

            // Hora mínima sugerida para reservas de hoy: 15 minutos después de la hora actual
            $horaMinimaHoy = now('America/Lima')->addMinutes(15)->format('H:i');
            @endphp

            <form action="{{ route('reservas.confirmar', $espacio) }}" method="POST">
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
                    <label for="fecha_reserva">Fecha de reserva</label>
                    <input type="date"
                        name="fecha_reserva"
                        id="fecha_reserva"
                        value="{{ old('fecha_reserva', $fechaMinima) }}"
                        min="{{ $fechaMinima }}"
                        max="{{ $fechaMaxima }}"
                        required>

                    <small style="display:block; margin-top:6px; color:#64748B;">
                        Solo se permiten reservas para hoy o como máximo mañana.
                    </small>
                </div>

                <div class="form-group">
                    <label for="hora_inicio">Hora de ingreso</label>
                    <input type="time"
                        name="hora_inicio"
                        id="hora_inicio"
                        value="{{ old('hora_inicio', $horaActual) }}"
                        required>

                    <small style="display:block; margin-top:6px; color:#64748B;">
                        Para reservas de hoy, la hora debe ser mayor a la hora actual.
                    </small>
                </div>

                <div class="form-group">
                    <label for="duracion_minutos">Duración</label>
                    <select name="duracion_minutos" id="duracion_minutos" required>
                        <option value="60" {{ old('duracion_minutos') == 60 ? 'selected' : '' }}>1 hora</option>
                        <option value="120" {{ old('duracion_minutos') == 120 ? 'selected' : '' }}>2 horas</option>
                        <option value="180" {{ old('duracion_minutos') == 180 ? 'selected' : '' }}>3 horas</option>
                        <option value="240" {{ old('duracion_minutos') == 240 ? 'selected' : '' }}>4 horas</option>
                    </select>
                </div>

                <div class="reservation-summary">
                    <p>
                        <strong>Importante:</strong> el sistema trabaja con reservas inmediatas.
                        Solo puedes reservar para hoy o como máximo mañana.
                    </p>
                    <p>
                        El monto será calculado automáticamente según el tipo de vehículo,
                        horario de ingreso, duración y tarifa activa.
                    </p>
                    <p>
                        En el siguiente paso verás el monto total antes de generar la reserva pendiente de pago.
                    </p>
                    <p>
                        Puedes consultar las tarifas vigentes aquí:
                        <a href="{{ route('public.tarifas') }}" target="_blank">
                            Ver tarifas
                        </a>
                    </p>
                </div>

                <div class="reservation-actions">
                    <button type="submit" class="btn btn-primary">
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
        const fechaInput = document.getElementById('fecha_reserva');
        const horaInput = document.getElementById('hora_inicio');

        const fechaHoy = "{{ $fechaMinima }}";
        const horaMinimaHoy = "{{ $horaMinimaHoy }}";

        function actualizarHoraMinima() {
            if (fechaInput.value === fechaHoy) {
                horaInput.min = horaMinimaHoy;

                if (horaInput.value && horaInput.value < horaMinimaHoy) {
                    horaInput.value = horaMinimaHoy;
                }
            } else {
                horaInput.removeAttribute('min');
            }
        }

        fechaInput.addEventListener('change', actualizarHoraMinima);
        actualizarHoraMinima();
    });
</script>

@endsection