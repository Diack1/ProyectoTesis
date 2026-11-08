<div class="entry-extras">
  <label class="entry-key-check"><input type="checkbox" name="llave_recibida" value="1" data-entry-key @checked(old('llave_recibida'))> Recibí la llave</label>
  <div class="form-group" data-entry-key-location @if(!old('llave_recibida')) hidden @endif><label for="ubicacion_llave">Casillero de la llave</label><select id="ubicacion_llave" name="ubicacion_llave"><option value="">Selecciona un casillero</option>@foreach($casilleros as $casillero)<option value="{{ $casillero['nombre'] }}" @selected(old('ubicacion_llave')===$casillero['nombre']) @disabled($casillero['ocupado'])>{{ $casillero['nombre'] }}{{ $casillero['ocupado'] ? ' · ocupado' : '' }}</option>@endforeach</select></div>
  <div class="form-group"><label for="salida_prevista">Salida aproximada (opcional)</label><input type="datetime-local" name="salida_prevista" id="salida_prevista" value="{{ old('salida_prevista') }}"></div>
</div>
