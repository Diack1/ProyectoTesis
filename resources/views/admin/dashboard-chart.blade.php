@php
    $first = $money ? 'paid' : 'entries';
    $second = $money ? 'refunds' : 'exits';
    $title = $money ? 'Cobros y reembolsos' : 'Entradas y salidas';
    $labels = $money ? ['Cobros brutos','Reembolsos'] : ['Entradas','Salidas'];
    $rows = $summary['rows'];
    $ceiling = max(4, ceil(($money ? $summary['maxMoney'] : $summary['maxActivity']) / 4) * 4);
    $step = 600 / count($rows);
    $points = [[], []];
    foreach ($rows as $i => $row) {
        foreach ([$first, $second] as $j => $key) {
            $points[$j][] = (60 + ($i + .5) * $step).','.round(240 - $row[$key] / $ceiling * 200, 2);
        }
    }
    $hasData = collect($rows)->sum($first) || collect($rows)->sum($second);
@endphp
<section class="summary-chart-panel" aria-label="{{ $title }}">
    <header class="summary-chart-heading">
        <span class="summary-icon"><x-icon :name="$money ? 'chart' : 'ticket'"/></span>
        <div><h3>{{ $title }} {{ $monthly ? 'por día' : 'por hora' }}</h3><p>{{ $money ? 'Importes en soles (S/). Reembolsos aprobados.' : 'Cantidad de vehículos que ingresan y salen.' }}</p></div>
    </header>
    <div class="summary-legend"><span><i></i>{{ $labels[0] }}</span><span class="{{ $money ? 'legend-refund' : 'legend-exit' }}"><i></i>{{ $labels[1] }}</span></div>
    @if(!$hasData)<p class="summary-empty">{{ $money ? 'No hay cobros ni reembolsos en este periodo.' : 'No hay entradas ni salidas en este periodo.' }}</p>@endif
    <div class="summary-plot-scroll" tabindex="0" role="region" aria-label="{{ $title }}: desplaza para ver todos los valores. Cifras disponibles en la tabla inferior.">
        <svg class="summary-plot" viewBox="0 0 680 285" role="img" aria-label="{{ $title }} {{ $monthly ? 'por día' : 'por hora' }}">
            @if($money)<defs><linearGradient id="income-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3989ff" stop-opacity=".6"/><stop offset="100%" stop-color="#3989ff" stop-opacity=".02"/></linearGradient></defs>@endif
            @for($tick = 0; $tick <= 4; $tick++)
                <line x1="60" x2="660" y1="{{ 240-$tick*50 }}" y2="{{ 240-$tick*50 }}" class="plot-grid"/>
                <text x="50" y="{{ 244-$tick*50 }}" text-anchor="end">{{ $money ? 'S/ ' : '' }}{{ number_format($ceiling*$tick/4,0) }}</text>
            @endfor
            @if($money)
                <polygon points="{{ 60+$step/2 }},240 {{ implode(' ', $points[0]) }} {{ 660-$step/2 }},240" fill="url(#income-fill)"/>
                @foreach($points as $j => $series)<polyline points="{{ implode(' ', $series) }}" fill="none" stroke="{{ $j ? '#ff718e' : '#3989ff' }}" stroke-width="2.5"/>@endforeach
            @endif
            @foreach($rows as $i => $row)
                @php($x = 60 + ($i+.5)*$step)
                <g class="plot-point" tabindex="0" aria-label="{{ $row['label'] }}: {{ $labels[0] }} {{ $row[$first] }}, {{ $labels[1] }} {{ $row[$second] }}{{ $money ? ' soles' : '' }}">
                    <title>{{ $row['label'] }} — {{ $labels[0] }}: {{ $row[$first] }} · {{ $labels[1] }}: {{ $row[$second] }}</title>
                    <rect x="{{ $x-$step/2 }}" y="35" width="{{ $step }}" height="210" class="plot-hit"/>
                    @foreach([$first,$second] as $j => $key)
                        @if($money)<circle cx="{{ $x }}" cy="{{ 240-$row[$key]/$ceiling*200 }}" r="3" fill="{{ $j ? '#ff718e' : '#3989ff' }}"/>
                        @else<rect x="{{ $x + ($j ? 1 : -$step*.34) }}" y="{{ 240-$row[$key]/$ceiling*200 }}" width="{{ $step*.3 }}" height="{{ $row[$key]/$ceiling*200 }}" rx="2" fill="{{ $j ? '#2dd4bf' : '#3989ff' }}"/>@endif
                    @endforeach
                </g>
                @if($i % ($monthly ? 2 : 3) === 0)<text x="{{ $x }}" y="265" text-anchor="middle">{{ $row['label'] }}</text>@endif
            @endforeach
        </svg>
    </div>
    @if($money)<div class="summary-financial-footer"><span>Cobros <strong>S/ {{ number_format($summary['paid'],2) }}</strong></span><span>Reembolsos <strong>S/ {{ number_format($summary['refunds'],2) }}</strong></span><span>Neto <strong>S/ {{ number_format($summary['net'],2) }}</strong></span></div>@endif
</section>
