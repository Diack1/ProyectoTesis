@extends('layouts.public')

@section('title', 'Estado de sensores')

@section('content')

<section class="section-sm">
    <div class="container">
        <div class="card">
            <span class="badge badge-info">Sensores externos</span>

            <h1 class="section-title" style="margin-top:16px;">
                Estado de sensores
            </h1>

            <p class="section-subtitle">
                Consulta la lectura recibida desde la API externa. El estado mostrado proviene solo de consultas GET realizadas por Laravel.
            </p>
        </div>
    </div>
</section>

<section class="section-sm" style="padding-top:0;">
    <div class="container">
        <div class="stats-grid" id="sensores-resumen">
            <div class="stat-card">
                <div class="stat-label">Total sensores</div>
                <div class="stat-value" data-summary="total">{{ $estadoSensores['resumen']['total'] ?? 0 }}</div>
                <div class="stat-help">Reportados por la API</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Libres</div>
                <div class="stat-value text-success" data-summary="libres">{{ $estadoSensores['resumen']['libres'] ?? 0 }}</div>
                <div class="stat-help">Disponibles</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Ocupados</div>
                <div class="stat-value text-danger" data-summary="ocupados">{{ $estadoSensores['resumen']['ocupados'] ?? 0 }}</div>
                <div class="stat-help">No disponibles</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Sin datos</div>
                <div class="stat-value text-warning" data-summary="sin_datos">{{ $estadoSensores['resumen']['sin_datos'] ?? 0 }}</div>
                <div class="stat-help">Lectura incompleta</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Actualizacion</div>
                <div class="stat-value sensor-refresh-state" id="sensores-refresh-icon">GET</div>
                <div class="stat-help" id="sensores-refresh-message">
                    {{ $estadoSensores['ok'] ? 'Actualizado: ' . ($estadoSensores['fetched_at'] ?? '-') : ($estadoSensores['message'] ?? 'No se pudo actualizar') }}
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">
        <div id="sensores-live" data-url="{{ route('sensores.estado.json') }}">
            @if(!$estadoSensores['ok'])
            <div class="alert-box alert-error">
                {{ $estadoSensores['message'] ?? 'No se pudo consultar el estado de sensores.' }}
            </div>
            @endif

            <div class="sensor-grid" id="sensores-grid">
                @forelse($estadoSensores['sensores'] ?? [] as $sensor)
                <article class="sensor-card sensor-card-{{ $sensor['estado'] }}">
                    <div class="sensor-card-header">
                        <div>
                            <h2>{{ $sensor['codigo'] }}</h2>
                            @if($sensor['espacio'])
                            <p>Espacio {{ $sensor['espacio']['codigo'] }}</p>
                            @else
                            <p>Sin espacio local asociado</p>
                            @endif
                        </div>

                        <span class="badge badge-{{ $sensor['estado'] === 'sin_datos' ? 'warning' : $sensor['estado'] }}">
                            <span class="sensor-state-icon" aria-hidden="true">
                                {!! $sensor['ocupado'] === true ? '&times;' : ($sensor['ocupado'] === false ? '&check;' : '-') !!}
                            </span>
                            {{ $sensor['estado_texto'] }}
                        </span>
                    </div>

                    <dl class="sensor-details">
                        <div>
                            <dt>Conexion</dt>
                            <dd>
                                <span class="sensor-dot {{ $sensor['conectado'] ? 'is-online' : 'is-offline' }}"></span>
                                {{ $sensor['conexion_texto'] }}
                            </dd>
                        </div>

                        <div>
                            <dt>Ultima lectura</dt>
                            <dd>{{ $sensor['ultima_lectura_formateada'] ?? 'Sin lectura' }}</dd>
                        </div>

                        @if($sensor['distancia_cm'] !== null)
                        <div>
                            <dt>Distancia</dt>
                            <dd>{{ $sensor['distancia_texto'] }}</dd>
                        </div>
                        @endif

                        @if($sensor['espacio'])
                        <div>
                            <dt>Estado local</dt>
                            <dd>{{ str_replace('_', ' ', $sensor['espacio']['estado_actual']) }}</dd>
                        </div>
                        @endif
                    </dl>

                    @if($sensor['puede_reservar'])
                    <a href="{{ $sensor['reserva_url'] }}" class="btn btn-success btn-sm">
                        Reservar espacio
                    </a>
                    @else
                    <span class="btn btn-secondary btn-sm sensor-disabled-action">
                        No disponible
                    </span>
                    @endif
                </article>
                @empty
                <div class="card">
                    {{ $estadoSensores['message'] ?? 'No hay sensores para mostrar.' }}
                </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    .sensor-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
    }

    .sensor-card {
        background: #FFFFFF;
        border: 1px solid var(--border);
        border-left: 5px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        min-height: 255px;
        padding: 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 18px;
    }

    .sensor-card-libre {
        border-left-color: var(--color-success);
    }

    .sensor-card-ocupado {
        border-left-color: var(--color-danger);
    }

    .sensor-card-sin_datos {
        border-left-color: var(--color-warning);
    }

    .sensor-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
    }

    .sensor-card h2 {
        margin: 0;
        color: var(--primary);
        font-size: 24px;
    }

    .sensor-card p {
        margin: 6px 0 0;
        color: var(--muted);
    }

    .sensor-state-icon {
        font-weight: 900;
        line-height: 1;
    }

    .sensor-details {
        margin: 0;
        display: grid;
        gap: 10px;
    }

    .sensor-details div {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        border-bottom: 1px solid #E2E8F0;
        padding-bottom: 8px;
    }

    .sensor-details div:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .sensor-details dt {
        color: var(--muted);
        font-weight: 800;
    }

    .sensor-details dd {
        margin: 0;
        color: var(--primary);
        font-weight: 700;
        text-align: right;
    }

    .sensor-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        display: inline-block;
        margin-right: 6px;
    }

    .sensor-dot.is-online {
        background: var(--color-success);
    }

    .sensor-dot.is-offline {
        background: var(--color-warning);
    }

    .sensor-refresh-state {
        font-size: 20px;
    }

    .sensor-disabled-action {
        cursor: not-allowed;
    }

    @media (max-width: 1100px) {
        .sensor-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 720px) {
        .sensor-grid {
            grid-template-columns: 1fr;
        }

        .sensor-card-header,
        .sensor-details div {
            flex-direction: column;
            align-items: flex-start;
        }

        .sensor-details dd {
            text-align: left;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const root = document.getElementById('sensores-live');
        const grid = document.getElementById('sensores-grid');
        const message = document.getElementById('sensores-refresh-message');
        const refreshIcon = document.getElementById('sensores-refresh-icon');

        if (!root || !grid || !message || !refreshIcon) {
            return;
        }

        const url = root.dataset.url;
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

        const updateSummary = function (resumen) {
            ['total', 'libres', 'ocupados', 'sin_datos'].forEach(function (key) {
                const element = document.querySelector('[data-summary="' + key + '"]');

                if (element) {
                    element.textContent = resumen && resumen[key] !== undefined ? resumen[key] : 0;
                }
            });
        };

        const renderSensors = function (sensores) {
            if (!Array.isArray(sensores) || sensores.length === 0) {
                grid.innerHTML = '<div class="card">No hay sensores para mostrar.</div>';
                return;
            }

            grid.innerHTML = sensores.map(function (sensor) {
                const estado = sensor.estado === 'ocupado' || sensor.estado === 'libre' ? sensor.estado : 'sin_datos';
                const badgeClass = estado === 'sin_datos' ? 'warning' : estado;
                const icon = sensor.ocupado === true ? '&times;' : (sensor.ocupado === false ? '&check;' : '-');
                const espacio = sensor.espacio;
                const distancia = sensor.distancia_cm !== null && sensor.distancia_cm !== undefined
                    ? '<div><dt>Distancia</dt><dd>' + escapeHtml(sensor.distancia_texto) + '</dd></div>'
                    : '';
                const estadoLocal = espacio
                    ? '<div><dt>Estado local</dt><dd>' + escapeHtml(String(espacio.estado_actual || '').replace(/_/g, ' ')) + '</dd></div>'
                    : '';
                const accion = sensor.puede_reservar && sensor.reserva_url
                    ? '<a href="' + escapeHtml(sensor.reserva_url) + '" class="btn btn-success btn-sm">Reservar espacio</a>'
                    : '<span class="btn btn-secondary btn-sm sensor-disabled-action">No disponible</span>';

                return '' +
                    '<article class="sensor-card sensor-card-' + escapeHtml(estado) + '">' +
                        '<div class="sensor-card-header">' +
                            '<div>' +
                                '<h2>' + escapeHtml(sensor.codigo) + '</h2>' +
                                '<p>' + (espacio ? 'Espacio ' + escapeHtml(espacio.codigo) : 'Sin espacio local asociado') + '</p>' +
                            '</div>' +
                            '<span class="badge badge-' + escapeHtml(badgeClass) + '">' +
                                '<span class="sensor-state-icon" aria-hidden="true">' + icon + '</span>' +
                                escapeHtml(sensor.estado_texto || 'Sin datos') +
                            '</span>' +
                        '</div>' +
                        '<dl class="sensor-details">' +
                            '<div><dt>Conexion</dt><dd><span class="sensor-dot ' + (sensor.conectado ? 'is-online' : 'is-offline') + '"></span>' + escapeHtml(sensor.conexion_texto || 'Sin datos disponibles') + '</dd></div>' +
                            '<div><dt>Ultima lectura</dt><dd>' + escapeHtml(sensor.ultima_lectura_formateada || 'Sin lectura') + '</dd></div>' +
                            distancia +
                            estadoLocal +
                        '</dl>' +
                        accion +
                    '</article>';
            }).join('');
        };

        const setMessage = function (text, failed) {
            message.textContent = text;
            refreshIcon.textContent = failed ? 'ERR' : 'GET';
            refreshIcon.classList.toggle('text-danger', Boolean(failed));
        };

        const refresh = async function () {
            if (loading) {
                return;
            }

            loading = true;
            setMessage('Actualizando...', false);

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
                    setMessage(payload.message || 'No se pudo actualizar', true);
                    return;
                }

                renderSensors(payload.sensores || []);
                updateSummary(payload.resumen || {});
                setMessage('Actualizado: ' + (payload.fetched_at || '-'), false);
            } catch (error) {
                setMessage('No se pudo actualizar', true);
            } finally {
                loading = false;
            }
        };

        window.setInterval(refresh, refreshMs);
    })();
</script>
@endpush
