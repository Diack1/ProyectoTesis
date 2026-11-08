@extends('layouts.admin')
@section('page-title','Informes')
@section('page-subtitle','Historial permanente de ventas, tickets y correcciones')
@section('content')
<div class="management-stats">
@foreach(['ventas'=>['Ventas','wallet'],'tickets'=>['Tickets','ticket'],'descuentos'=>['Descuentos','chart'],'anuladas'=>['Boletas anuladas','edit']] as $ruta=>[$nombre,$icono])
<a class="admin-page-card" href="{{ route('admin.informes.'.$ruta) }}"><h2><x-icon :name="$icono"/> {{ $nombre }}</h2><span>Consultar historial →</span></a>
@endforeach
</div>
<div class="admin-page-card"><a class="btn btn-secondary" href="{{ route('admin.reportes.index') }}">Estadísticas de operación y ocupación</a></div>
@endsection
