@php
$owner = auth()->user()->esSuperAdmin();
$metrics = [
 ['Entradas','entries','car','Vehículos ingresados'],
 ['Salidas','exits','exit','Estadías finalizadas'],
 ['Reservas atendidas','reserved','clock','Con ingreso registrado'],
 ['Cobros del periodo (brutos)','paid','wallet','Antes de reembolsos'],
];
if ($owner) {
 $metrics[] = ['Reembolsos aprobados','refunds','arrow','Procesados en este periodo'];
 $metrics[] = ['Neto del periodo','net','chart','Cobros menos reembolsos'];
}
@endphp
<section class="card dashboard-summary" aria-labelledby="summary-title">
<div class="summary-heading"><div><span class="eyebrow">ACTIVIDAD DE LA COCHERA</span><h2 id="summary-title">{{ $monthly ? 'Resumen mensual' : 'Resumen del día' }}</h2><p>{{ $summary['start']->format($monthly ? 'm/Y' : 'd/m/Y') }} · Hora local de la cochera</p></div>
@if($owner)
<form method="get" class="summary-filter">
<label>Periodo<select name="periodo"><option value="dia" @selected(!$monthly)>Día</option><option value="mes" @selected($monthly)>Mes</option></select></label>
<label>Fecha de referencia<input type="date" name="fecha" value="{{ $summary['start']->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required></label><button class="btn btn-primary">Consultar</button>
</form>@endif</div>
<div class="summary-numbers">
@foreach($metrics as [$label,$key,$icon,$hint])
<article class="summary-metric {{ $key === 'net' ? 'summary-metric-net' : '' }}">
<div class="summary-metric-heading"><span class="summary-icon"><x-icon :name="$icon"/></span><h3>{{ $label }}</h3></div>
<strong>{{ in_array($key,['paid','refunds','net']) ? 'S/ '.number_format($summary[$key],2) : number_format($summary[$key]) }}</strong><small>{{ $hint }}</small>
</article>
@endforeach
</div>
<div class="summary-charts {{ $owner ? 'summary-charts-owner' : '' }}">
@include('admin.dashboard-chart', ['money'=>false])
@if($owner) @include('admin.dashboard-chart', ['money'=>true]) @endif
</div>
<p class="summary-note">Pendientes de revisión ahora: <strong>S/ {{ number_format($summary['pending'],2) }}</strong>. No se incluyen en los cobros.@if($owner) El neto no descuenta los gastos del negocio.@endif</p>
<details><summary>Ver cifras del gráfico</summary><div class="table-wrapper"><table><thead><tr><th>Hora / día</th><th>Entradas</th><th>Salidas</th><th>Cobros</th>@if(auth()->user()->esSuperAdmin())<th>Reembolsos</th>@endif</tr></thead><tbody>@foreach($summary['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['entries'] }}</td><td>{{ $row['exits'] }}</td><td>S/ {{ number_format($row['paid'],2) }}</td>@if(auth()->user()->esSuperAdmin())<td>S/ {{ number_format($row['refunds'],2) }}</td>@endif</tr>@endforeach</tbody></table></div></details>
</section>
