@if($reserva?->pago_al_llegar && $reserva->estado === 'pendiente_pago')
<fieldset><legend>Cobro al llegar · S/ {{ number_format($reserva->monto_total,2) }}</legend>
<p>Verifica placa y reserva. Escanear el QR no confirma un pago.</p>
<div class="form-group"><label for="metodo_pago">Medio recibido</label><select name="metodo_pago" id="metodo_pago" required><option value="">Selecciona</option>@foreach(['efectivo'=>'Efectivo','yape'=>'Yape presencial','plin'=>'Plin presencial'] as $value=>$label)<option value="{{ $value }}" @selected(old('metodo_pago')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="form-group"><label for="efectivo_recibido">Efectivo recibido (S/), si paga en efectivo</label><input type="number" name="efectivo_recibido" id="efectivo_recibido" min="0" step="0.01" value="{{ old('efectivo_recibido') }}"></div>
<div class="form-group"><label for="referencia_pago">Número de operación, si paga por Yape o Plin</label><input name="referencia_pago" id="referencia_pago" maxlength="80" value="{{ old('referencia_pago') }}"></div>
<label><input type="checkbox" name="pago_verificado" value="1" required> Recibí y verifiqué el pago en caja o en la cuenta del negocio.</label>
</fieldset>
@endif
