@extends('layouts.public')

@section('title', 'Tarifas vigentes')

@section('content')

<section class="page-heading">
    <div class="container">
        <div class="page-heading-card">
            <span class="badge badge-info">Tarifas vigentes</span>

            <h1 class="section-title">
                Consulta de tarifas por tipo de vehículo
            </h1>

            <p class="section-subtitle">
                El usuario no selecciona directamente la tarifa. El sistema calcula automáticamente
                el monto según el tipo de vehículo, la hora de ingreso, la duración y las tarifas activas.
            </p>

            <div class="mt-2">
                <a href="{{ route('public.disponibilidad') }}" class="btn btn-primary">
                    Reservar un espacio
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top:24px;">
    <div class="container">

        @forelse($vehiculoTipos as $vehiculoTipo)
        <div class="tariff-section">
            <h2 class="tariff-title">
                {{ $vehiculoTipo->nombre }}
            </h2>

            @if($vehiculoTipo->tarifas->count() > 0)
            <div class="tariff-grid">
                @foreach($vehiculoTipo->tarifas as $tarifa)
                <div class="tariff-card">
                    <span class="badge badge-{{ $tarifa->tipo_tarifa }}">
                        {{ str_replace('_', ' ', $tarifa->tipo_tarifa) }}
                    </span>

                    <h3>{{ $tarifa->nombre }}</h3>

                    @if($tarifa->tipo_tarifa === 'por_hora')
                    <div class="tariff-price">
                        S/ {{ number_format($tarifa->monto_por_hora, 2) }} / hora
                    </div>

                    @elseif($tarifa->tipo_tarifa === 'fraccion')
                    <div class="tariff-price">
                        S/ {{ number_format($tarifa->monto_por_fraccion, 2) }}
                    </div>

                    <p class="tariff-detail">
                        Cada {{ $tarifa->minutos_fraccion }} minutos
                    </p>

                    @elseif($tarifa->tipo_tarifa === 'diaria')
                    <div class="tariff-price">
                        S/ {{ number_format($tarifa->monto_base, 2) }} / día
                    </div>

                    @elseif($tarifa->tipo_tarifa === 'nocturna')
                    <div class="tariff-price">
                        S/ {{ number_format($tarifa->monto_base, 2) }}
                    </div>

                    <p class="tariff-detail">
                        Horario:
                        {{ $tarifa->hora_inicio ? substr($tarifa->hora_inicio, 0, 5) : '-' }}
                        -
                        {{ $tarifa->hora_fin ? substr($tarifa->hora_fin, 0, 5) : '-' }}
                    </p>
                    @endif

                    <p class="tariff-detail">
                        <strong>Tiempo mínimo:</strong>
                        {{ $tarifa->tiempo_minimo_minutos }} minutos
                    </p>

                    <p class="tariff-detail">
                        <strong>Tolerancia:</strong>
                        {{ $tarifa->tolerancia_minutos }} minutos
                    </p>

                    <p class="tariff-detail">
                        <strong>Penalidad por exceso:</strong>
                        S/ {{ number_format($tarifa->penalidad_por_fraccion, 2) }}
                    </p>
                </div>
                @endforeach
            </div>
            @else
            <div class="card">
                No hay tarifas activas para este tipo de vehículo.
            </div>
            @endif
        </div>
        @empty
        <div class="card">
            No existen tipos de vehículo activos.
        </div>
        @endforelse

    </div>
</section>

@endsection