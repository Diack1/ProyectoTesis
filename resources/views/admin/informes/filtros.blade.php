<form method="get" class="card sale-filters" aria-label="Filtrar informe">
    <label>Día<input type="date" name="dia" value="{{ request('dia') }}"></label>
    <label>Desde<input type="date" name="desde" value="{{ request('desde') }}"></label>
    <label>Hasta<input type="date" name="hasta" value="{{ request('hasta') }}"></label>
    @if($ventasFiltro ?? false)
        <label>Buscar<input name="buscar" value="{{ request('buscar') }}" placeholder="Número, cliente o placa"></label>
        <label>Comprobante<select name="tipo"><option value="">Todos</option>@foreach(['boleta'=>'Boleta','factura'=>'Factura'] as $clave=>$valor)<option value="{{ $clave }}" @selected(request('tipo')===$clave)>{{ $valor }}</option>@endforeach</select></label>
        <label>Estado<select name="estado"><option value="">Todos</option>@foreach(['vigente'=>'Vigente','corregida'=>'Corregida','anulada'=>'Anulada'] as $clave=>$valor)<option value="{{ $clave }}" @selected(request('estado')===$clave)>{{ $valor }}</option>@endforeach</select></label>
        <label>Medio<select name="metodo"><option value="">Todos</option>@foreach(['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','manual'=>'Otros'] as $clave=>$valor)<option value="{{ $clave }}" @selected(request('metodo')===$clave)>{{ $valor }}</option>@endforeach</select></label>
    @endif
    <button class="btn btn-primary">Filtrar</button>
</form>
