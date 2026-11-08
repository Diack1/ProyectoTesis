@extends('layouts.admin')
@section('page-title','Abonados')
@section('page-subtitle','Clientes con espacio fijo y periodos pagados.')
@push('styles') @vite('resources/css/pages/abonados.css') @endpush
@section('content')
@include('admin.estadias.messages')
<div class="subscriber-toolbar"><a class="btn btn-primary" href="{{ route('admin.abonados.create') }}">+ Agregar abonado</a></div>
<section class="card subscriber-list">
    <form method="get" class="subscriber-search"><label for="subscriber-query">Buscar abonados</label><div><input id="subscriber-query" name="buscar" placeholder="Nombre, DNI, teléfono o placa" value="{{ request('buscar') }}"><button class="btn btn-secondary">Buscar</button></div></form>
    <nav class="subscriber-filters" aria-label="Filtrar abonados">@foreach(['todos'=>'Todos','al_dia'=>'Al día','proximos'=>'Próximos a vencer','vencidos'=>'Vencidos'] as $value=>$label)<a href="{{ route('admin.abonados.index',['estado'=>$value,'buscar'=>request('buscar')]) }}" class="{{ $estado===$value?'active':'' }}">{{ $label }}</a>@endforeach</nav>
    <div class="table-wrapper"><table class="data-table subscriber-table"><thead><tr><th>Abonado</th><th>Placas</th><th>Espacio</th><th>Periodo</th><th>Estado</th><th></th></tr></thead><tbody>
    @forelse($clientes as $cliente)
    @php($periodo=$cliente->abonos->first())
    @php($estadoActual=!$periodo || $periodo->fin->lte(now()) ? 'vencido' : ($periodo->fin->lte(now()->addDays(7)) ? 'proximo' : 'al_dia'))
    <tr><td data-label="Abonado"><strong>{{ $cliente->nombre }}</strong><small>{{ $cliente->dni ? 'DNI '.$cliente->dni.' · ' : '' }}{{ $cliente->telefono }}</small></td><td data-label="Placas">{{ implode(', ',$cliente->placas) }}</td><td data-label="Espacio">{{ $periodo?->espacio?->codigo ?? '—' }}</td><td data-label="Periodo">{{ $periodo?->inicio?->format('d/m/Y') ?? '—' }} → {{ $periodo?->fin?->format('d/m/Y') ?? '—' }}</td><td data-label="Estado"><span class="subscriber-status {{ $estadoActual }}">{{ ['al_dia'=>'Al día','proximo'=>'Próximo a vencer','vencido'=>'Vencido'][$estadoActual] }}</span></td><td><a class="btn btn-secondary" href="{{ route('admin.abonados.show',$cliente) }}">Ver detalle</a> <a class="btn btn-secondary" href="{{ route('admin.abonados.edit',$cliente) }}">Editar</a></td></tr>
    @empty<tr><td colspan="6">No hay abonados para este filtro.</td></tr>@endforelse
    </tbody></table></div>
    {{ $clientes->links() }}
</section>
@endsection
