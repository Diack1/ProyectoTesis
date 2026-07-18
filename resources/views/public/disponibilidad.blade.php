@extends('layouts.public')

@section('title', 'Disponibilidad de espacios')

@section('content')

<section class="section-sm">
    <div class="container">
        @if(session('error'))
        <div class="card" style="border-left:5px solid var(--danger); margin-bottom:24px;">
            <strong style="color:var(--danger);">Aviso:</strong>
            <p style="margin:6px 0 0;">{{ session('error') }}</p>
        </div>
        @endif

        <div class="card">
            <span class="badge badge-info">Disponibilidad en tiempo real</span>

            <h1 class="section-title" style="margin-top:16px;">
                Disponibilidad de espacios
            </h1>

            <p class="section-subtitle">
                Consulta los espacios registrados en la Cochera Tentaci&oacute;n. Solo los espacios con estado
                <strong>libre</strong> pueden seleccionarse para iniciar una reserva.
            </p>

            @if(isset($estadoSensores) && !$estadoSensores['ok'])
            <p class="section-subtitle sensor-sync-message" style="margin-top:14px;">
                {{ $estadoSensores['message'] ?? 'No se pudo actualizar el estado de sensores.' }}
            </p>
            @endif
        </div>
    </div>
</section>

<section class="section-sm" style="padding-top:0;">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de espacios</div>
                <div class="stat-value" data-availability-summary="total">{{ $totalEspacios ?? 0 }}</div>
                <div class="stat-help">Capacidad registrada</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Libres</div>
                <div class="stat-value text-success" data-availability-summary="libre">{{ $espaciosLibres ?? 0 }}</div>
                <div class="stat-help">Disponibles para reservar</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Ocupados</div>
                <div class="stat-value text-danger" data-availability-summary="ocupado">{{ $espaciosOcupados ?? 0 }}</div>
                <div class="stat-help">Detectados como ocupados</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Reservados</div>
                <div class="stat-value text-warning" data-availability-summary="reservado">{{ $espaciosReservados ?? 0 }}</div>
                <div class="stat-help">Reservas activas</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Mantenimiento</div>
                <div class="stat-value" data-availability-summary="mantenimiento">{{ $espaciosMantenimiento ?? 0 }}</div>
                <div class="stat-help">Temporalmente bloqueados</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Estado desconocido</div>
                <div class="stat-value text-warning" data-availability-summary="sin_datos">{{ $espaciosSinDatos ?? 0 }}</div>
                <div class="stat-help">Sin lectura valida</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Sin configuracion</div>
                <div class="stat-value text-warning" data-availability-summary="sin_configuracion">{{ $espaciosSinConfiguracion ?? 0 }}</div>
                <div class="stat-help">Tipos o tarifas pendientes</div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">
        <div class="flex-between flex-wrap mb-3">
            <div>
                <h2 class="section-title">Selecciona un espacio</h2>
                <p class="section-subtitle disponibilidad-refresh" id="disponibilidad-refresh-message">
                    Actualizacion automatica cada 10 segundos.
                </p>
                <p class="section-subtitle">
                    Los espacios libres se muestran con borde verde y pueden reservarse. Los dem&aacute;s estados est&aacute;n bloqueados.
                </p>
            </div>

            <a href="{{ route('public.tarifas') }}" class="btn btn-secondary">
                Ver tarifas
            </a>
        </div>

        <div class="space-grid" id="disponibilidad-space-grid" data-url="{{ route('sensores.estado.json') }}">
            @forelse($espacios as $espacio)
            @php
                $sensorLocal = $espacio->sensor ?? null;
                $sensorExterno = $sensorLocal ? ($sensoresPorCodigo->get($sensorLocal->codigo_sensor) ?? null) : null;
                $estadoTarjeta = $disponibilidadPorEspacio[$espacio->id] ?? [
                    'estado_visual' => 'sin_datos',
                    'estado_texto' => 'Sin conexion',
                    'logicamente_disponible' => false,
                    'sensor_disponible' => false,
                    'puede_reservar' => false,
                ];
                $estadoVisual = $estadoTarjeta['estado_visual'];
                $estadoBadge = in_array($estadoVisual, ['sin_datos', 'sin_tipos', 'sin_tarifa'], true) ? 'warning' : $estadoVisual;
                $puedeReservar = $estadoTarjeta['puede_reservar'];
            @endphp

            <a href="{{ $puedeReservar ? route('reservas.create', $espacio) : '#' }}"
                class="space-link disponibilidad-space-link {{ !$puedeReservar ? 'is-disabled' : '' }}"
                data-space-code="{{ $espacio->codigo }}"
                data-sensor-code="{{ $sensorLocal->codigo_sensor ?? '' }}"
                data-estado-espacio="{{ $espacio->estado_actual }}"
                data-logicamente-disponible="{{ $estadoTarjeta['logicamente_disponible'] ? 'true' : 'false' }}"
                data-configuracion-disponible="{{ ($estadoTarjeta['configuracion_disponible'] ?? false) ? 'true' : 'false' }}"
                data-bloqueo-motivo="{{ $estadoTarjeta['bloqueo_motivo'] ?? '' }}"
                data-sensor-disponible="{{ $estadoTarjeta['sensor_disponible'] ? 'true' : 'false' }}"
                data-visual-state="{{ $estadoVisual }}"
                data-reserve-url="{{ route('reservas.create', $espacio) }}">
                <div class="space-card {{ $estadoVisual }} {{ !$puedeReservar ? 'no-disponible' : '' }}">
                    <div>
                        <div class="flex-between">
                            <h3>{{ $espacio->codigo }}</h3>

                            <span class="badge badge-{{ $estadoBadge }}" data-role="estado-badge">
                                {{ $estadoTarjeta['estado_texto'] }}
                            </span>
                        </div>

                        <p>{{ $espacio->descripcion }}</p>

                        <p>
                            <strong>Veh&iacute;culos:</strong>
                            @forelse($espacio->vehiculoTipos as $tipo)
                            {{ $tipo->nombre }}{{ !$loop->last ? ', ' : '' }}
                            @empty
                            No configurado
                            @endforelse
                        </p>

                        @if($sensorLocal)
                        <p class="sensor-reading-line" data-role="sensor-reading">
                            <strong>Sensor:</strong>
                            {{ $sensorLocal->codigo_sensor }}
                            @if($sensorExterno)
                                - {{ $sensorExterno['estado_texto'] }}
                                @if($sensorExterno['ultima_lectura_formateada'])
                                    ({{ $sensorExterno['ultima_lectura_formateada'] }})
                                @endif
                            @endif
                        </p>
                        @endif

                        @if(!$puedeReservar && !empty($estadoTarjeta['bloqueo_motivo']))
                        <p class="sensor-reading-line" data-role="bloqueo-motivo">
                            <strong>Motivo:</strong> {{ $estadoTarjeta['bloqueo_motivo'] }}
                        </p>
                        @else
                        <p class="sensor-reading-line" data-role="bloqueo-motivo"></p>
                        @endif
                    </div>

                    <div class="mt-2">
                        <span class="btn {{ $puedeReservar ? 'btn-success' : 'btn-secondary' }} btn-sm" data-role="action-label">
                            {{ $puedeReservar ? 'Reservar espacio' : 'No disponible' }}
                        </span>
                    </div>
                </div>
            </a>
            @empty
            <div class="card">
                No hay espacios registrados por el momento.
            </div>
            @endforelse
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    .disponibilidad-refresh,
    .sensor-sync-message {
        font-size: 13px;
        margin-top: 10px;
    }

    .disponibilidad-space-link.is-disabled {
        cursor: not-allowed;
    }

    .space-card.sin_datos,
    .space-card.sin_tipos,
    .space-card.sin_tarifa {
        border-left-color: var(--color-warning);
        opacity: 0.82;
        cursor: not-allowed;
    }

    .sensor-reading-line {
        font-size: 13px;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const grid = document.getElementById('disponibilidad-space-grid');
        const message = document.getElementById('disponibilidad-refresh-message');

        if (!grid || !message) {
            return;
        }

        const url = grid.dataset.url;
        const refreshMs = 10000;
        let loading = false;

        const escapeHtml = function (value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const normalizeText = function (state) {
            return String(state || 'sin datos').replace(/_/g, ' ');
        };

        const updateSummary = function () {
            const totals = {
                total: 0,
                libre: 0,
                ocupado: 0,
                reservado: 0,
                mantenimiento: 0,
                sin_datos: 0,
                sin_configuracion: 0,
            };

            grid.querySelectorAll('.disponibilidad-space-link').forEach(function (link) {
                const state = link.dataset.visualState || link.dataset.estadoEspacio || 'sin_datos';
                totals.total += 1;

                if (state === 'sin_tipos' || state === 'sin_tarifa') {
                    totals.sin_configuracion += 1;
                } else if (totals[state] !== undefined) {
                    totals[state] += 1;
                }
            });

            Object.keys(totals).forEach(function (key) {
                const element = document.querySelector('[data-availability-summary="' + key + '"]');

                if (element) {
                    element.textContent = totals[key];
                }
            });
        };

        const applySensorState = function (link, sensor) {
            const card = link.querySelector('.space-card');
            const badge = link.querySelector('[data-role="estado-badge"]');
            const action = link.querySelector('[data-role="action-label"]');
            const reading = link.querySelector('[data-role="sensor-reading"]');
            const bloqueo = link.querySelector('[data-role="bloqueo-motivo"]');
            const estadoEspacio = link.dataset.estadoEspacio;
            const logicamenteDisponible = link.dataset.logicamenteDisponible === 'true';
            const configuracionDisponible = link.dataset.configuracionDisponible === 'true';
            let visualState = estadoEspacio;
            let canReserve = false;
            let visualText = normalizeText(visualState);
            let bloqueoMotivo = link.dataset.bloqueoMotivo || '';

            if (!sensor || sensor.datos_disponibles !== true) {
                visualState = 'sin_datos';
                visualText = 'Estado desconocido';
            } else if (sensor.ocupado === true) {
                visualState = 'ocupado';
                visualText = 'Ocupado';
            } else if (sensor.ocupado === false) {
                canReserve = sensor.puede_reservar === true && logicamenteDisponible;
                visualState = canReserve
                    ? 'libre'
                    : (sensor.estado_visual || (!configuracionDisponible ? 'sin_tipos' : (estadoEspacio === 'mantenimiento' ? 'mantenimiento' : 'reservado')));
                visualText = canReserve ? 'Libre' : (sensor.estado_visual_texto || normalizeText(visualState));
                bloqueoMotivo = sensor.bloqueo_motivo || bloqueoMotivo;
            }

            link.dataset.visualState = visualState;
            link.dataset.sensorDisponible = sensor && sensor.datos_disponibles === true && sensor.ocupado === false ? 'true' : 'false';
            link.dataset.bloqueoMotivo = bloqueoMotivo;
            link.href = canReserve ? link.dataset.reserveUrl : '#';
            link.classList.toggle('is-disabled', !canReserve);

            if (card) {
                card.classList.remove('libre', 'ocupado', 'reservado', 'mantenimiento', 'sin_datos', 'sin_tipos', 'sin_tarifa', 'no-disponible');
                card.classList.add(visualState);
                card.classList.toggle('no-disponible', !canReserve);
            }

            if (badge) {
                const warningStates = ['sin_datos', 'sin_tipos', 'sin_tarifa'];
                badge.className = 'badge badge-' + (warningStates.includes(visualState) ? 'warning' : visualState);
                badge.textContent = visualText;
            }

            if (action) {
                action.className = 'btn ' + (canReserve ? 'btn-success' : 'btn-secondary') + ' btn-sm';
                action.textContent = canReserve ? 'Reservar espacio' : 'No disponible';
            }

            if (reading && sensor) {
                reading.innerHTML = '<strong>Sensor:</strong> ' + escapeHtml(sensor.codigo) + ' - ' + escapeHtml(sensor.estado_texto || 'Sin datos') +
                    (sensor.ultima_lectura_formateada ? ' (' + escapeHtml(sensor.ultima_lectura_formateada) + ')' : '');
            }

            if (bloqueo) {
                bloqueo.innerHTML = !canReserve && bloqueoMotivo
                    ? '<strong>Motivo:</strong> ' + escapeHtml(bloqueoMotivo)
                    : '';
            }
        };

        grid.addEventListener('click', function (event) {
            const link = event.target.closest('.disponibilidad-space-link');

            if (link && link.classList.contains('is-disabled')) {
                event.preventDefault();
            }
        });

        const refresh = async function () {
            if (loading) {
                return;
            }

            loading = true;
            message.textContent = 'Actualizando sensores...';

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    cache: 'no-store',
                });

                const payload = await response.json();

                if (!payload.ok) {
                    message.textContent = payload.message || 'No se pudo actualizar';
                    return;
                }

                const sensorsByCode = {};

                (payload.sensores || []).forEach(function (sensor) {
                    sensorsByCode[sensor.codigo] = sensor;
                });

                grid.querySelectorAll('.disponibilidad-space-link').forEach(function (link) {
                    const sensorCode = link.dataset.sensorCode;

                    if (sensorCode) {
                        applySensorState(link, sensorsByCode[sensorCode]);
                    }
                });

                updateSummary();
                message.textContent = 'Actualizado: ' + (payload.fetched_at || '-');
            } catch (error) {
                message.textContent = 'No se pudo actualizar';
            } finally {
                loading = false;
            }
        };

        updateSummary();
        window.setInterval(refresh, refreshMs);
    })();
</script>
@endpush
