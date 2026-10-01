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
                        <option value="{{ $tipo->id }}" {{ old('vehiculo_tipo_id', request()->query('vehiculo_tipo_id')) == $tipo->id ? 'selected' : '' }}>
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
                        <option value="60" {{ old('duracion_minutos', request()->query('duracion_minutos')) == 60 ? 'selected' : '' }}>1 hora</option>
                        <option value="120" {{ old('duracion_minutos', request()->query('duracion_minutos')) == 120 ? 'selected' : '' }}>2 horas</option>
                        <option value="180" {{ old('duracion_minutos', request()->query('duracion_minutos')) == 180 ? 'selected' : '' }}>3 horas</option>
                        <option value="240" {{ old('duracion_minutos', request()->query('duracion_minutos')) == 240 ? 'selected' : '' }}>4 horas</option>
                    </select>
                </div>

                <aside class="reservation-summary" id="resumenTarifaReserva"><h2>Tu reserva de un vistazo</h2><p><strong>Espacio:</strong> {{ $espacio->codigo }}</p><p><strong>Vehículo:</strong> <span data-booking-vehicle>Por elegir</span></p><p><strong>Placa:</strong> <span data-booking-plate>Por completar</span></p><p><strong>Llegada:</strong> <span>Dentro de los 15 minutos posteriores a la aprobación del pago</span></p>
                    <p><strong>Tarifa:</strong> <span data-role="tarifa-nombre">Selecciona un tipo de vehiculo.</span></p>
                    <p><strong>Duración:</strong> <span data-role="tarifa-duracion">-</span></p>
                    <p><strong>Monto estimado:</strong> <span data-role="tarifa-monto">-</span></p>
                    <p><strong>Si te quedas más tiempo:</strong> <span data-role="tarifa-tolerancia">-</span></p>
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
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formNuevaReserva');
    const button = document.getElementById('btnNuevaReserva');
    const vehicle = document.getElementById('vehiculo_tipo_id');
    const duration = document.getElementById('duracion_minutos');
    const summary = document.getElementById('resumenTarifaReserva');
    const prices = @json($tarifasIniciales);
    let pending;
    async function quote() {
        pending?.abort();
        const controller = new AbortController(); pending = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        const name = summary.querySelector('[data-role="tarifa-nombre"]');
        const amount = summary.querySelector('[data-role="tarifa-monto"]');
        const preview = prices[vehicle.value]?.[duration.value];
        name.textContent = preview?.precio_unitario || 'Consultando precio…';
        amount.textContent = preview ? 'S/ ' + preview.total + ' (estimado)' : '—';
        summary.querySelector('[data-role="tarifa-duracion"]').textContent = duration.options[duration.selectedIndex].text;
        summary.querySelector('[data-role="tarifa-tolerancia"]').textContent = preview?.exceso || 'Por verificar';
        try {
            const params = new URLSearchParams({vehiculo_tipo_id:vehicle.value,duracion_minutos:duration.value});
            const response = await fetch(@json(route('public.cotizar', $espacio, false))+'?'+params, {headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No se pudo consultar la tarifa.');
            if (pending !== controller) return;
            name.textContent = data.precio_unitario;
            amount.textContent = 'S/ '+data.total;
            summary.querySelector('[data-role="tarifa-duracion"]').textContent = duration.options[duration.selectedIndex].text;
            summary.querySelector('[data-role="tarifa-tolerancia"]').textContent = data.exceso;
        } catch (error) {
            if (pending === controller && !preview) name.textContent = 'La consulta no respondió. Al continuar, el servidor comprobará la tarifa antes de confirmar.';
        } finally { clearTimeout(timeout); }
    }
    vehicle.addEventListener('change', quote); duration.addEventListener('change', quote); quote();
    form?.addEventListener('submit', () => { if (button) { button.disabled = true; button.textContent = 'Procesando…'; } });
    window.addEventListener('pageshow', () => { if (button) { button.disabled = false; button.textContent = 'Revisar reserva'; } });
});
</script>

@endsection

@push('scripts')
<script src="/js/reserva-publica.js?v={{ filemtime(public_path('js/reserva-publica.js')) }}" defer></script>
@endpush
