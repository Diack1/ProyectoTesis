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
                Consulta los espacios registrados en la Cochera Tentación. Solo los espacios con estado
                <strong>libre</strong> pueden seleccionarse para iniciar una reserva.
            </p>
        </div>
    </div>
</section>

<section class="section-sm" style="padding-top:0;">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de espacios</div>
                <div class="stat-value">{{ $totalEspacios ?? 0 }}</div>
                <div class="stat-help">Capacidad registrada</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Libres</div>
                <div class="stat-value text-success">{{ $espaciosLibres ?? 0 }}</div>
                <div class="stat-help">Disponibles para reservar</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Ocupados</div>
                <div class="stat-value text-danger">{{ $espaciosOcupados ?? 0 }}</div>
                <div class="stat-help">Detectados como ocupados</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Reservados</div>
                <div class="stat-value text-warning">{{ $espaciosReservados ?? 0 }}</div>
                <div class="stat-help">Reservas activas</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Mantenimiento</div>
                <div class="stat-value">{{ $espaciosMantenimiento ?? 0 }}</div>
                <div class="stat-help">Temporalmente bloqueados</div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">
        <div class="flex-between flex-wrap mb-3">
            <div>
                <h2 class="section-title">Selecciona un espacio</h2>
                <p class="section-subtitle">
                    Los espacios libres se muestran con borde verde y pueden reservarse. Los demás estados están bloqueados.
                </p>
            </div>

            <a href="{{ route('public.tarifas') }}" class="btn btn-secondary">
                Ver tarifas
            </a>
        </div>

        <div class="space-grid">
            @forelse($espacios as $espacio)
            @if($espacio->estado_actual === 'libre')
            <a href="{{ route('reservas.create', $espacio) }}" class="space-link">
                <div class="space-card libre">
                    <div>
                        <div class="flex-between">
                            <h3>{{ $espacio->codigo }}</h3>
                            <span class="badge badge-libre">Libre</span>
                        </div>

                        <p>{{ $espacio->descripcion }}</p>

                        <p>
                            <strong>Vehículos:</strong>
                            @forelse($espacio->vehiculoTipos as $tipo)
                            {{ $tipo->nombre }}{{ !$loop->last ? ', ' : '' }}
                            @empty
                            No configurado
                            @endforelse
                        </p>
                    </div>

                    <div class="mt-2">
                        <span class="btn btn-success btn-sm">
                            Reservar espacio
                        </span>
                    </div>
                </div>
            </a>
            @else
            <div class="space-card {{ $espacio->estado_actual }} no-disponible">
                <div>
                    <div class="flex-between">
                        <h3>{{ $espacio->codigo }}</h3>

                        <span class="badge badge-{{ $espacio->estado_actual }}">
                            {{ str_replace('_', ' ', $espacio->estado_actual) }}
                        </span>
                    </div>

                    <p>{{ $espacio->descripcion }}</p>

                    <p>
                        <strong>Vehículos:</strong>
                        @forelse($espacio->vehiculoTipos as $tipo)
                        {{ $tipo->nombre }}{{ !$loop->last ? ', ' : '' }}
                        @empty
                        No configurado
                        @endforelse
                    </p>
                </div>

                <div class="mt-2">
                    <span class="btn btn-secondary btn-sm">
                        No disponible
                    </span>
                </div>
            </div>
            @endif
            @empty
            <div class="card">
                No hay espacios registrados por el momento.
            </div>
            @endforelse
        </div>
    </div>
</section>

@endsection