@php
    $rows = $summary['rows'];
    $values = array_map(fn ($row) => (float) $row[$chart], $rows);
    $low = min(0, min($values));
    $high = max(1, max($values));
    $span = $high - $low;
    $step = count($rows) > 1 ? 700 / (count($rows) - 1) : 700;
    $zeroY = round(235 - (0 - $low) / $span * 180, 2);
    $points = [];
    foreach ($values as $i => $value) $points[] = [60 + $i * $step, 235 - ($value - $low) / $span * 180];
    $slopes = [];
    for ($i = 0; $i < count($points) - 1; $i++) {
        $slopes[] = ($points[$i + 1][1] - $points[$i][1]) / ($points[$i + 1][0] - $points[$i][0]);
    }
    $tangents = array_fill(0, count($points), 0.0);
    $tangents[0] = $slopes[0];
    $tangents[count($points) - 1] = $slopes[count($slopes) - 1];
    for ($i = 1; $i < count($points) - 1; $i++) {
        if ($slopes[$i - 1] * $slopes[$i] > 0) {
            $tangents[$i] = 2 * $slopes[$i - 1] * $slopes[$i] / ($slopes[$i - 1] + $slopes[$i]);
        }
    }
    $curve = sprintf('M %.2f %.2f', $points[0][0], $points[0][1]);
    for ($i = 0; $i < count($points) - 1; $i++) {
        $distance = ($points[$i + 1][0] - $points[$i][0]) / 3;
        $curve .= sprintf(' C %.2f %.2f, %.2f %.2f, %.2f %.2f',
            $points[$i][0] + $distance, $points[$i][1] + $tangents[$i] * $distance,
            $points[$i + 1][0] - $distance, $points[$i + 1][1] - $tangents[$i + 1] * $distance,
            $points[$i + 1][0], $points[$i + 1][1]);
    }
    $area = $curve.sprintf(' L %.2f %.2f L %.2f %.2f Z', $points[count($points) - 1][0], $zeroY, $points[0][0], $zeroY);
@endphp
@if(!collect($values)->contains(fn ($value) => $value != 0))<p class="activity-empty">No hay {{ mb_strtolower($chartLabels[$chart]) }} en este periodo y turno.</p>@endif
<div class="activity-plot-scroll" role="region" tabindex="0" aria-label="Gráfico por hora; cifras detalladas debajo de las tarjetas">
<svg class="activity-plot" viewBox="0 0 810 270" role="img" aria-label="{{ $chartLabels[$chart] }} por hora">
    <defs><linearGradient id="activity-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3989ff" stop-opacity=".38"/><stop offset="100%" stop-color="#3989ff" stop-opacity=".02"/></linearGradient></defs>
    @for($tick=0;$tick<=4;$tick++)
        @php($value=$low+$span*$tick/4)
        @php($y=235-$tick*45)
        <line x1="60" x2="760" y1="{{ $y }}" y2="{{ $y }}" class="activity-grid"/>
        <text x="50" y="{{ $y+4 }}" text-anchor="end">{{ $chart==='ingreso' ? 'S/ ' : '' }}{{ number_format($value,$chart==='ingreso'?0:0) }}</text>
    @endfor
    <line x1="60" x2="760" y1="{{ $zeroY }}" y2="{{ $zeroY }}" class="activity-baseline"/>
    <path d="{{ $area }}" fill="url(#activity-fill)"/>
    <path d="{{ $curve }}" fill="none" stroke="#3989ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    @foreach($rows as $i=>$row)
        @php($x=round(60+$i*$step,2))
        @php($y=round(235-($values[$i]-$low)/$span*180,2))
        <g class="activity-point" tabindex="0" aria-label="{{ $row['label'] }}: {{ $chart==='ingreso' ? 'S/ '.number_format($values[$i],2) : number_format($values[$i]) }}">
            <title>{{ $row['label'] }} · {{ $chartLabels[$chart] }}: {{ $chart==='ingreso' ? 'S/ '.number_format($values[$i],2) : number_format($values[$i]) }}</title>
            <rect x="{{ $x-$step/2 }}" y="45" width="{{ $step }}" height="195" fill="transparent"/>
            <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#78b4ff" stroke="#0d1d30" stroke-width="2"/>
        </g>
        @if($i % (count($rows)>15 ? 3 : 2) === 0 || $i===count($rows)-1)<text x="{{ $x }}" y="258" text-anchor="middle">{{ $row['label'] }}</text>@endif
    @endforeach
</svg>
</div>
