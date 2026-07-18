@extends('layouts.public')

@section('title', 'Cochera Tentación - Cochera inteligente')

@section('content')

<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="hero-badge">
                Sistema inteligente de disponibilidad y reservas
            </div>

            <h1>Encuentra y reserva tu espacio antes de llegar</h1>

            <p>
                Consulta la disponibilidad de espacios en tiempo real, selecciona una cochera libre
                y asegura tu reserva desde cualquier dispositivo.
            </p>

            <div class="hero-actions">
                <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary btn-lg">
                    Ver disponibilidad
                </a>

                <a href="{{ route('public.disponibilidad') }}" class="btn btn-light btn-lg">
                    Reservar ahora
                </a>
            </div>
        </div>

        <div class="hero-panel">
            <h3>Estado actual de la cochera</h3>

            <div class="hero-row">
                <span>Total de espacios</span>
                <strong>{{ $totalEspacios ?? 0 }}</strong>
            </div>

            <div class="hero-row">
                <span>Espacios libres</span>
                <strong class="text-success">{{ $espaciosLibres ?? 0 }}</strong>
            </div>

            <div class="hero-row">
                <span>Espacios ocupados</span>
                <strong class="text-danger">{{ $espaciosOcupados ?? 0 }}</strong>
            </div>

            <div class="hero-row">
                <span>Espacios reservados</span>
                <strong class="text-warning">{{ $espaciosReservados ?? 0 }}</strong>
            </div>

            <div class="hero-row">
                <span>En mantenimiento</span>
                <strong>{{ $espaciosMantenimiento ?? 0 }}</strong>
            </div>
        </div>
    </div>
</section>

<section class="section-sm">
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

<section class="section">
    <div class="container">
        <div class="flex-between flex-wrap mb-3">
            <div>
                <h2 class="section-title">Disponibilidad de espacios</h2>
                <p class="section-subtitle">
                    Revisa los espacios disponibles. Solo los espacios con estado libre pueden reservarse.
                </p>
            </div>

            <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary">
                Ver todos los espacios
            </a>
        </div>

        <div class="space-grid">
            @forelse($espacios->take(8) as $espacio)
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
                            {{ $espacio->estado_actual }}
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
                No hay espacios registrados.
            </div>
            @endforelse
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="card">
            <div class="flex-between flex-wrap">
                <div>
                    <h2 class="section-title">Tarifas claras antes de reservar</h2>
                    <p class="section-subtitle">
                        Consulta las tarifas vigentes por tipo de vehículo, horario, duración,
                        tolerancia y penalidad por exceso.
                    </p>
                </div>

                <a href="{{ route('public.tarifas') }}" class="btn btn-primary">
                    Ver tarifas
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">¿Cómo funciona?</h2>
        <p class="section-subtitle">
            El proceso de reserva está diseñado para ser rápido, seguro y fácil de usar.
        </p>

        <div class="stats-grid">
            <div class="card">
                <span class="badge badge-info">1</span>
                <h3>Consulta disponibilidad</h3>
                <p class="text-muted">
                    Visualiza el estado actual de los espacios de la cochera.
                </p>
            </div>

            <div class="card">
                <span class="badge badge-info">2</span>
                <h3>Selecciona un espacio</h3>
                <p class="text-muted">
                    Elige una tarjeta libre y revisa los datos de tu reserva.
                </p>
            </div>

            <div class="card">
                <span class="badge badge-info">3</span>
                <h3>Inicia sesión</h3>
                <p class="text-muted">
                    Si no tienes cuenta, puedes registrarte antes de continuar.
                </p>
            </div>

            <div class="card">
                <span class="badge badge-info">4</span>
                <h3>Reserva y paga</h3>
                <p class="text-muted">
                    Confirma tu reserva y realiza el pago para asegurar el espacio.
                </p>
            </div>

            <div class="card">
                <span class="badge badge-success">✓</span>
                <h3>Espacio asegurado</h3>
                <p class="text-muted">
                    Tu reserva queda registrada en el sistema.
                </p>
            </div>
        </div>
    </div>
</section>

@endsection