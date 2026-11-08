@extends('layouts.public')

@section('title', 'Tarifas vigentes')

@section('content')

<section class="page-heading">
    <div class="container">
        <div class="page-heading-card">
            <span class="badge badge-info">Tarifas vigentes</span>

            <h1 class="section-title">
                Elige con el precio claro
            </h1>

            <p class="section-subtitle">
                Consulta el precio según tu vehículo y horario. Antes de pagar verás el total de tu reserva.
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

                    @elseif($tarifa->tipo_tarifa === 'bloque_horas')
                    <div class="tariff-price">S/ {{ number_format($tarifa->monto_base, 2) }} / {{ (int)($tarifa->tiempo_minimo_minutos / 60) }} horas</div>
                    <p class="tariff-detail">El exceso usa los intervalos de la tarifa por hora.</p>

                    @elseif($tarifa->tipo_tarifa === 'mensual')
                    <div class="tariff-price">S/ {{ number_format($tarifa->monto_base, 2) }} / mes</div>

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

                    @if($tarifa->tipo_tarifa !== 'mensual')<p class="tariff-detail">
                        <strong>Tiempo mínimo:</strong>
                        {{ $tarifa->tiempo_minimo_minutos }} minutos
                    </p>

                    <p class="tariff-detail">
                        <strong>Tolerancia de exceso:</strong>
                        {{ $tarifa->tipo_tarifa === 'bloque_horas' ? ($vehiculoTipo->tarifas->firstWhere('tipo_tarifa','por_hora')?->tolerancia_minutos ?? 0) : $tarifa->tolerancia_minutos }} minutos
                    </p>

                    <p class="tariff-detail">
                        <strong>Penalidad por exceso:</strong>
                        S/ {{ number_format($tarifa->tipo_tarifa === 'bloque_horas' ? ($vehiculoTipo->tarifas->firstWhere('tipo_tarifa','por_hora')?->monto_por_fraccion ?? 0) : $tarifa->penalidad_por_fraccion, 2) }}
                    </p>@endif
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
