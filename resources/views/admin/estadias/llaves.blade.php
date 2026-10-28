<fieldset><legend>Abono y custodia de llaves</legend>
<div class="form-group"><label for="abono_id">Abono vigente (solo para el vehículo del abonado)</label><select id="abono_id" name="abono_id"><option value="">Ingreso por hora / reserva</option>@foreach($abonos as $a)<option value="{{ $a->id }}" @selected(old('abono_id',request('abono_id'))==$a->id)>{{ $a->espacio->codigo }} · {{ $a->abonado->nombre }} · {{ implode(', ',$a->abonado->placas) }}</option>@endforeach</select><small>Selecciona también su espacio y tipo de vehículo. El servidor verifica vigencia, placa y pago.</small></div>
<p>Para ocupar temporalmente el espacio de un abonado ausente, registra la autorización y la llave. No podrás reubicar el vehículo sin ambos datos.</p>
<label><input type="checkbox" name="autoriza_movimiento" value="1" @checked(old('autoriza_movimiento'))> El cliente autoriza mover su vehículo.</label>
<label><input type="checkbox" name="llave_recibida" value="1" @checked(old('llave_recibida'))> Recibí la llave.</label>
<div class="form-group"><label for="ubicacion_llave">Ubicación de la llave</label><input id="ubicacion_llave" name="ubicacion_llave" maxlength="100" placeholder="Casillero 12" value="{{ old('ubicacion_llave') }}"></div>
<div class="form-group"><label for="salida_prevista">Salida aproximada (opcional)</label><input type="datetime-local" name="salida_prevista" id="salida_prevista" value="{{ old('salida_prevista') }}"></div>
</fieldset>
