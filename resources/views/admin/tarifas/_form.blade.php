@php($kind=old('tipo_tarifa',$tarifa?->tipo_tarifa ?? 'por_hora'))
<div class="form-grid">
    <div class="form-group"><label for="vehiculo_tipo_id">Tipo de vehículo</label><select name="vehiculo_tipo_id" id="vehiculo_tipo_id" required><option value="">Seleccionar vehículo</option>@foreach($vehiculoTipos as $tipo)<option value="{{ $tipo->id }}" @selected(old('vehiculo_tipo_id',$tarifa?->vehiculo_tipo_id)==$tipo->id)>{{ $tipo->codigo === 'camioneta' ? 'Miniván' : $tipo->nombre }}</option>@endforeach</select></div>
    <div class="form-group"><label for="tipo_tarifa">Tipo de tarifa</label><select name="tipo_tarifa" id="tipo_tarifa" required><option value="por_hora" @selected($kind==='por_hora')>Por hora</option><option value="bloque_horas" @selected($kind==='bloque_horas')>Bloque de horas</option><option value="mensual" @selected($kind==='mensual')>Abonados · mensual</option></select></div>
</div>
<div class="form-grid">
    <div class="form-group" data-rate-field="block"><label for="horas_bloque">Duración del bloque (horas)</label><input id="horas_bloque" name="horas_bloque" type="number" min="2" max="720" value="{{ old('horas_bloque',$tarifa?->tipo_tarifa==='bloque_horas' ? (int)($tarifa->tiempo_minimo_minutos/60) : 12) }}"></div>
    <div class="form-group"><label for="precio" id="price-label">Precio de 1 hora (S/)</label><input id="precio" name="precio" type="number" min="0.01" max="9999" step="0.01" required value="{{ old('precio',$tarifa ? ($tarifa->tipo_tarifa==='por_hora' ? $tarifa->monto_por_hora : $tarifa->monto_base) : '') }}"></div>
</div>
<div data-rate-field="hour" class="form-grid">
    <div class="form-group"><label for="minutos_fraccion">Intervalo adicional (minutos)</label><input id="minutos_fraccion" name="minutos_fraccion" type="number" min="1" max="60" value="{{ old('minutos_fraccion',$tarifa?->minutos_fraccion ?? 15) }}"></div>
    <div class="form-group"><label for="monto_por_fraccion">Monto por intervalo (S/)</label><input id="monto_por_fraccion" name="monto_por_fraccion" type="number" min="0.01" step="0.01" value="{{ old('monto_por_fraccion',$tarifa?->monto_por_fraccion ?? 1) }}"></div>
    <div class="form-group"><label for="tolerancia_minutos">Tolerancia (minutos)</label><input id="tolerancia_minutos" name="tolerancia_minutos" type="number" min="0" max="120" value="{{ old('tolerancia_minutos',$tarifa?->tolerancia_minutos ?? 0) }}"></div>
</div>
<p class="text-muted" data-rate-note="block" hidden>El bloque utiliza la tolerancia y los intervalos adicionales de la tarifa por hora activa del mismo vehículo.</p>
<p class="text-muted" data-rate-note="monthly" hidden>El importe se multiplicará por los meses contratados.</p>
<label class="checkbox-row"><input type="checkbox" name="activo" value="1" @checked(old('activo',$tarifa?->activo ?? true))> Tarifa activa</label>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const kind=document.getElementById('tipo_tarifa'), price=document.getElementById('price-label');
 const update=()=>{const value=kind.value;document.querySelectorAll('[data-rate-field]').forEach(el=>{const show=el.dataset.rateField==='hour' ? value==='por_hora' : value==='bloque_horas';el.hidden=!show;el.querySelectorAll('input').forEach(input=>input.disabled=!show)});document.querySelector('[data-rate-note="block"]').hidden=value!=='bloque_horas';document.querySelector('[data-rate-note="monthly"]').hidden=value!=='mensual';price.textContent=value==='por_hora'?'Precio de 1 hora (S/)':value==='bloque_horas'?'Precio del bloque (S/)':'Precio de 1 mes (S/)'};
 kind.addEventListener('change',update);update();
});
</script>
