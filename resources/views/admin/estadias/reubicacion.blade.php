<section class="card"><h2>Ubicación y reubicaciones</h2>
@if($estadia->abono)<p><strong>Ingreso de abonado:</strong> <a href="{{ route('admin.abonados.show',$estadia->abono->abonado_id) }}">{{ $estadia->abono->abonado->nombre }}</a>. Periodo de ingreso: {{ $estadia->abono->inicio->format('d/m/Y') }} → {{ $estadia->abono->fin->format('d/m/Y H:i') }}. El cálculo de salida considera las renovaciones pagadas y cobra por hora los intervalos vencidos.</p>@endif
<p>Ubicación actual: <strong>{{ $estadia->espacio->codigo }}</strong>. Llave: {{ $estadia->llave_recibida ? $estadia->ubicacion_llave : 'No recibida' }}.</p>
@if(!$estadia->hora_salida && !$estadia->abono_id)
@if(!$estadia->autoriza_movimiento || !$estadia->llave_recibida)<p>Este ingreso no tiene autorización y llave registradas. No se permite reubicar desde aquí.</p>
@elseif($destinos->isEmpty())<p role="status">No hay un espacio disponible para reubicar. La ubicación actual se mantiene.</p>
@else<form method="post" action="{{ route('admin.estadias.reubicar',$estadia) }}">@csrf<input type="hidden" name="origen_id" value="{{ $estadia->espacio_id }}">
<div class="form-group"><label for="destino_id">Destino libre</label><select name="destino_id" id="destino_id" required>@foreach($destinos as $d)<option value="{{ $d->id }}">{{ $d->codigo }}</option>@endforeach</select></div>
<div class="form-group"><label for="motivo_movimiento">Motivo</label><input id="motivo_movimiento" name="motivo" required minlength="5" maxlength="500" value="{{ old('motivo','Llegada del abonado') }}"></div>
<label><input type="checkbox" name="movimiento_confirmado" value="1" required> Moví físicamente el vehículo y comprobé el destino.</label><p>Se conservan el ticket, el contador y la tarifa. El destino se vuelve a comprobar al confirmar.</p><button class="btn btn-primary">Confirmar reubicación</button></form>@endif
@endif
@foreach($estadia->reubicaciones()->with('origen','destino','operador')->latest()->get() as $m)<p>{{ $m->created_at->format('d/m/Y H:i') }} · {{ $m->origen->codigo }} → {{ $m->destino->codigo }} · {{ $m->operador->name }}<br>{{ $m->motivo }}</p>@endforeach
</section>
