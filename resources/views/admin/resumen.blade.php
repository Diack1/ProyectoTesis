@extends('layouts.admin')
@section('page-title','Resumen del día')
@section('page-subtitle','Actividad y cobros reales del estacionamiento')
@push('styles')<link rel="stylesheet" href="{{ asset('css/activity-dashboard.css') }}">@endpush
@section('content')
@php
    $owner = auth()->user()->esSuperAdmin();
    $chartLabels = ['ventas'=>'Cantidad de ventas','ingreso'=>'Ingreso','salidas'=>'Salidas'];
    $shiftLabels = ['todos'=>'Todos los turnos','manana'=>'Turno mañana · 06:00–13:00','tarde'=>'Turno tarde · 13:00–19:00','noche'=>'Turno noche · 19:00–06:00'];
    $cards = [
        ['Ventas en efectivo','S/ '.number_format($summary['methods']['efectivo'],2),'wallet'],
        ['Ventas con tarjeta','S/ '.number_format($summary['methods']['tarjeta'],2),'wallet'],
        ['Ventas con Yape','S/ '.number_format($summary['methods']['yape'],2),'wallet'],
        ['Ventas con Plin','S/ '.number_format($summary['methods']['plin'],2),'wallet'],
        ['Otros medios de pago','S/ '.number_format($summary['methods']['manual'],2),'wallet'],
        ['Total de ventas','S/ '.number_format($summary['sales_total'],2),'chart'],
        ['Total de descuentos','S/ '.number_format($summary['discounts'],2),'ticket'],
        ['Total de egresos','S/ '.number_format($summary['expenses'],2),'exit'],
        ['Total de ingresos extra','S/ '.number_format($summary['extra_income'],2),'wallet'],
        ['Total de salidas',number_format($summary['exits']),'car'],
    ];
@endphp
<div class="activity-dashboard">
    <div class="activity-back"><a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">← Volver al mapa</a></div>
    <section class="activity-shell" aria-labelledby="activity-title">
        <header class="activity-head">
            <div><span class="eyebrow">ACTIVIDAD DE PARKE’O</span><h2 id="activity-title">{{ $monthly ? 'Resumen mensual' : 'Resumen del día' }}</h2>
                <p>Datos del {{ $summary['start']->format('d/m/Y') }}@if(!$summary['start']->isSameDay($summary['end'])) al {{ $summary['end']->format('d/m/Y') }}@endif · {{ $shiftLabels[$shift] }}</p>
            </div>
            <span class="activity-updated">Actualizado {{ now()->format('d/m/Y H:i') }}</span>
        </header>

        <form method="get" class="activity-filters" id="activity-filters">
            <label>Gráfico<select name="grafico" onchange="this.form.requestSubmit()">@foreach($chartLabels as $key=>$label)<option value="{{ $key }}" @selected($chart===$key)>{{ $label }}</option>@endforeach</select></label>
            <label>Turno<select name="turno" onchange="this.form.requestSubmit()">@foreach($shiftLabels as $key=>$label)<option value="{{ $key }}" @selected($shift===$key)>{{ $label }}</option>@endforeach</select></label>
            @if($owner)
                <label>Fecha<select name="modo" id="activity-mode"><option value="dia" @selected($mode==='dia')>Una fecha</option><option value="rango" @selected($mode==='rango')>Rango de fechas</option></select></label>
                <label class="activity-date-day" @if($mode==='rango') hidden @endif>Día<input type="date" name="fecha" value="{{ $summary['start']->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}"></label>
                <label class="activity-date-range" @if($mode!=='rango') hidden @endif>Desde<input type="date" name="desde" value="{{ $summary['start']->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" @if($mode==='rango') required @endif></label>
                <label class="activity-date-range" @if($mode!=='rango') hidden @endif>Hasta<input type="date" name="hasta" value="{{ min($summary['end'], today())->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" @if($mode==='rango') required @endif></label>
            @else
                <span class="activity-current-day">{{ today()->format('d/m/Y') }} · turno actual</span>
            @endif
            <button class="btn btn-primary" type="submit">Actualizar</button>
        </form>

        <div class="activity-chart-layout">
            <aside class="activity-peaks" aria-label="Horas destacadas">
                <div><span>Hora punta</span><strong>{{ $peak ?? '—' }}</strong></div>
                <div><span>Hora más libre</span><strong>{{ $quiet ?? '—' }}</strong></div>
            </aside>
            <section class="activity-chart" aria-labelledby="activity-chart-title">
                <div class="activity-chart-heading"><div><h3 id="activity-chart-title">{{ $chartLabels[$chart] }} por hora</h3><p>{{ $shiftLabels[$shift] }} · {{ $summary['sales_count'] }} ventas registradas</p></div><span class="activity-chart-key"><i></i>{{ $chartLabels[$chart] }}</span></div>
                @include('admin.activity-chart')
            </section>
        </div>

        <div class="activity-cards" aria-label="Resumen de ventas del periodo">
            @foreach($cards as [$label,$amount,$icon])
            <article class="activity-card"><span class="activity-card-icon"><x-icon :name="$icon"/></span><span>{{ $label }}</span><strong>{{ $amount }}</strong></article>
            @endforeach
        </div>
        <p class="activity-note">El turno noche termina a las 06:00 del día siguiente. Total de ventas corresponde a comprobantes vigentes; los medios de pago e ingreso reflejan cobros, devoluciones y ajustes. El selector cambia únicamente el gráfico.</p>
        <details class="activity-data"><summary>Ver cifras por hora</summary><div class="table-wrapper"><table><thead><tr><th>Hora</th><th>Ventas</th><th>Ingreso</th><th>Salidas</th></tr></thead><tbody>@foreach($summary['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['ventas'] }}</td><td>S/ {{ number_format($row['ingreso'],2) }}</td><td>{{ $row['salidas'] }}</td></tr>@endforeach</tbody></table></div></details>
    </section>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/activity-dashboard.js') }}" defer></script>@endpush
