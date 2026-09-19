<div class="form-group">
<label for="modo_monitoreo">Fuente del estado físico</label>
<select id="modo_monitoreo" name="modo_monitoreo" class="form-control">
<option value="manual" @selected(old('modo_monitoreo',$espacio->modo_monitoreo ?? 'manual')==='manual')>Control manual</option>
<option value="sensor" @selected(old('modo_monitoreo',$espacio->modo_monitoreo ?? 'manual')==='sensor')>Sensor instalado</option>
</select><p>Selecciona sensor solo cuando esté instalado y probado. Las reservas se gestionan por separado.</p>
</div>
<div class="form-group"><label><input type="checkbox" name="incluido_estudio" value="1" @checked(old('incluido_estudio',$espacio->incluido_estudio ?? false))> Espacio seleccionado para la tesis</label></div>
<div class="form-group"><label for="codigo_sensor">Código del sensor (opcional en control manual)</label><input id="codigo_sensor" name="codigo_sensor" class="form-control" value="{{ old('codigo_sensor',$espacio->sensor->codigo_sensor ?? '') }}"></div>
<div class="form-group"><label for="tipo_sensor">Modelo del sensor</label><input id="tipo_sensor" name="tipo_sensor" class="form-control" list="modelos-sensor" value="{{ old('tipo_sensor',$espacio->sensor->tipo_sensor ?? '') }}"><datalist id="modelos-sensor"><option value="A02 RS485"><option value="JSN-SR04T"><option value="simulado"></datalist></div>
