@extends('layouts.admin')
@section('page-title', 'Espacio '.$espacio->codigo)
@section('page-subtitle', $estadia ? $estadia->placa.' · '.$estadia->codigo_ticket : ucfirst(str_replace('_',' ',$espacio->estado_actual)))
@push('styles') @vite(['resources/css/pages/cobro.css','resources/css/pages/operacion-espacio.css','resources/css/pages/ingreso-presencial.css','resources/css/pages/ticket-entrada.css']) @endpush
@section('content')
@include('admin.estadias.messages')
<div class="space-workspace">
<main class="space-content card" id="space-content">
    @if($vista==='comprobante' && $finalizada)
        @if($ventaComprobante)
        @include('admin.estadias.comprobante-pagado',['urlImprimir'=>route('admin.impresoras.estadia',$estadia)])
        @else
        <div class="space-panel-heading"><span class="eyebrow">SALIDA REGISTRADA</span><h2>Comprobante de pago</h2><p>{{ $estadia->hora_salida->format('d/m/Y H:i:s') }} · {{ ucfirst($estadia->tipo_comprobante ?? 'boleta') }}</p></div>
        <dl class="ticket-details"><div><dt>Cliente</dt><dd>{{ $estadia->clienteCobro ? $estadia->clienteCobro->nombres.' '.$estadia->clienteCobro->apellidos : 'Cliente general' }}</dd></div><div><dt>Documento</dt><dd>{{ $estadia->clienteCobro ? $estadia->clienteCobro->tipo_documento.' '.$estadia->clienteCobro->numero_documento : '—' }}</dd></div><div><dt>Placa</dt><dd>{{ $estadia->placa }}</dd></div><div><dt>Espacio</dt><dd>{{ $espacio->codigo }}</dd></div><div><dt>Tiempo</dt><dd>{{ $estadia->minutos_cobrados }} min</dd></div><div><dt>Tarifa</dt><dd>{{ $estadia->tarifa_snapshot['nombre'] ?? 'Registrada' }}</dd></div><div><dt>Total</dt><dd>S/ {{ number_format($estadia->monto_total,2) }}</dd></div><div><dt>Vuelto</dt><dd>S/ {{ number_format($estadia->vuelto ?? 0,2) }}</dd></div>@foreach($estadia->pagosSalida as $pago)<div><dt>{{ ucfirst($pago->metodo_pago) }}</dt><dd>S/ {{ number_format($pago->monto,2) }}</dd></div>@endforeach</dl>
        <div class="space-button-row"><form method="post" action="{{ route('admin.impresoras.estadia',$estadia) }}" target="_blank">@csrf<button class="btn btn-primary"><x-icon name="print"/>Imprimir comprobante</button></form><a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Volver al mapa</a></div>
        @endif
    @elseif($vista==='cobrar' && $estadia)
        @if($liquidacion)
            @include('admin.estadias.cobro-salida')
        @else<div class="space-panel-heading"><h2>Inicio de cobro pendiente</h2><p>El sensor aún no confirmó que el vehículo se estacionó. Consulta el ticket para registrar una contingencia si corresponde.</p><a class="btn btn-secondary" href="{{ route('admin.estadias.show',$estadia) }}">Revisar inicio del cobro</a></div>@endif
    @elseif($vista==='ticket' && $estadia)
        <div class="space-panel-heading"><span class="eyebrow">{{ $finalizada ? 'TICKET FINALIZADO' : 'TICKET ACTIVO' }}</span><h2>{{ $estadia->codigo_ticket }}</h2><p>Comprueba los datos registrados y entrega o imprime el ticket.</p></div>
        @if(request()->boolean('detalle'))
            <div class="ticket-inline-detail"><iframe class="ticket-detail-frame" title="Ticket completo {{ $estadia->codigo_ticket }}" src="{{ route('admin.estadias.ticket',[$estadia,'incrustado'=>1]) }}"></iframe><a class="btn btn-secondary" href="{{ route('admin.espacios.operar',['espacio'=>$espacio,'vista'=>'ticket'] + ($finalizada ? ['ticket'=>$estadia->id] : [])) }}">Volver al ticket</a></div>
        @else
            @include('admin.estadias.ticket-entrada-card')
        @endif
    @elseif($vista==='editar' && $estadia)
        <div class="space-panel-heading"><span class="eyebrow">CORRECCIÓN AUDITADA</span><h2>Editar registro</h2><p>Modifica placa, tipo o tarifa. El ingreso real y el ticket se conservan; la nueva tarifa recalculará el cobro.</p></div>
        <form method="post" action="{{ route('admin.estadias.corregir',$estadia) }}" class="space-form" id="edit-stay-form">@csrf
            <div class="space-form-grid"><div class="form-group"><label for="edit-placa">Placa</label><input id="edit-placa" name="placa" required maxlength="10" value="{{ old('placa',$estadia->placa) }}" data-original="{{ $estadia->placa }}" @readonly($estadia->reserva_id)></div><div class="form-group"><label for="edit-type">Tipo de vehículo</label><select id="edit-type" name="vehiculo_tipo_id" data-original="{{ $estadia->vehiculo_tipo_id }}" data-original-name="{{ $estadia->vehiculoTipo->nombre }}" @disabled($estadia->reserva_id || $estadia->abono_id)>@foreach($espacio->vehiculoTipos->where('activo',true) as $tipo)<option value="{{ $tipo->id }}" @selected(old('vehiculo_tipo_id',$estadia->vehiculo_tipo_id)==$tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select></div><div class="form-group"><label for="edit-rate">Tarifa seleccionada</label><select id="edit-rate" name="tarifa_id" data-original-name="{{ $estadia->tarifa_snapshot['nombre'] ?? 'Registrada' }}" @disabled($estadia->reserva_id || $estadia->abono_id)><option value="">Conservar: {{ $estadia->tarifa_snapshot['nombre'] ?? 'tarifa registrada' }}</option>@foreach($tarifasEdicion as $tarifa)<option value="{{ $tarifa->id }}" data-type="{{ $tarifa->vehiculo_tipo_id }}" @selected(old('tarifa_id')==$tarifa->id)>{{ $tarifa->nombre }} · {{ str_replace('_',' ',$tarifa->tipo_tarifa) }}</option>@endforeach</select></div></div>
            @if($estadia->reserva_id || $estadia->abono_id)<p class="text-muted">La reserva o abono vinculado fija el tipo y la tarifa. La placa de una reserva se corrige desde su registro.</p>@endif
            <div class="form-group"><label for="edit-motivo">Motivo de la corrección</label><textarea id="edit-motivo" name="motivo" minlength="5" maxlength="500" required>{{ old('motivo') }}</textarea></div>
            <label class="checkbox-row"><input type="checkbox" name="correccion_confirmada" value="1" required> Revisé los datos y confirmo la corrección.</label><button class="btn btn-primary">Revisar y guardar</button>
        </form>
    @elseif($vista==='mover' && $estadia)
        <div class="space-panel-heading"><span class="eyebrow">REUBICACIÓN</span><h2>Mover vehículo</h2><p>Elige en el mapa un destino libre compatible. Se conservarán el ticket, la tarifa y el tiempo acumulado.</p></div>
        @if($estadia->abono_id || !$estadia->llave_recibida || !$estadia->inicio_cobro)<p class="alert alert-info">Este ticket no cumple las condiciones para reubicarse. Verifica la llave y el inicio del cobro.</p>
        @elseif($destinos->isEmpty())<p class="alert alert-info">No hay destinos libres y compatibles en este momento.</p>
        @else<form method="post" action="{{ route('admin.estadias.reubicar',$estadia) }}" data-confirm-form="¿Confirmas que moviste físicamente el vehículo al espacio seleccionado?">@csrf<input type="hidden" name="origen_id" value="{{ $espacio->id }}"><input type="hidden" name="desde_mapa" value="1">@if(request()->boolean('retorno_ingreso'))<input type="hidden" name="retorno_ingreso" value="1">@endif
            <div class="parking-scroll"><div class="parking-lot parking-lot-techos destination-lot" role="radiogroup" aria-label="Espacio de destino">
                <div class="lot-office">Oficina</div><div class="lot-services">Servicios</div><div class="lot-lane">↑<br>CIRCULACIÓN<br>↓</div><div class="lot-gate">ENTRADA / SALIDA ↑</div>
                @foreach(range(1,41) as $n)@php([$x,$y,$w,$h] = \App\Services\PlanoEspacios::posicion($n))@php($destino = $destinos->first(fn ($d) => \App\Services\PlanoEspacios::numero($d->codigo) === $n))
                @if($destino)<label class="destination-space parking-space" style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%"><input type="radio" name="destino_id" value="{{ $destino->id }}" required><strong>{{ $destino->codigo }}</strong><small>Libre</small></label>
                @else<button type="button" class="parking-space" style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%" disabled><strong>{{ 'T'.str_pad((string)$n,2,'0',STR_PAD_LEFT) }}</strong><small>No disponible</small></button>@endif
                @endforeach
            </div></div>
            <div class="form-group"><label for="move-motive">Motivo del movimiento</label><input id="move-motive" name="motivo" minlength="5" maxlength="500" required value="{{ old('motivo','Reubicación autorizada') }}"></div>@unless($estadia->autoriza_movimiento)<label class="checkbox-row"><input type="checkbox" name="autoriza_movimiento_confirmada" value="1" required> Confirmo que el cliente autorizó mover su vehículo.</label>@endunless<label class="checkbox-row"><input type="checkbox" name="movimiento_confirmado" value="1" required> Moví físicamente el vehículo y comprobé el destino.</label><button class="btn btn-primary">Confirmar reubicación</button>
        </form>@endif
    @elseif($vista==='historial' && $estadia)
        <div class="space-panel-heading"><span class="eyebrow">TRAZABILIDAD DEL TICKET</span><h2>Historial</h2><p>Ingreso, correcciones y movimientos registrados para {{ $estadia->codigo_ticket }}.</p></div>
        <ol class="space-timeline"><li><time>{{ $estadia->hora_ingreso->format('d/m/Y H:i:s') }}</time><strong>Ingreso · {{ $estadia->placa }}</strong><span>{{ $estadia->espacio->codigo }} · {{ $estadia->operadorIngreso?->name ?? 'Personal' }}</span></li>
            @if($estadia->inicio_cobro)<li><time>{{ $estadia->inicio_cobro->format('d/m/Y H:i:s') }}</time><strong>Inicio del cobro</strong><span>{{ ['sensor'=>'Detectado por sensor','manual'=>'Ticket de ingreso','manual_contingencia'=>'Contingencia manual'][$estadia->fuente_inicio] ?? $estadia->fuente_inicio }}</span>@if($estadia->motivo_inicio_manual)<small>{{ $estadia->motivo_inicio_manual }}</small>@endif</li>@endif
            @foreach($estadia->auditorias->sortBy('created_at') as $auditoria)<li><time>{{ $auditoria->created_at->format('d/m/Y H:i:s') }}</time><strong>Corrección · {{ $auditoria->usuario?->name }}</strong><span>Motivo: {{ $auditoria->motivo }}</span>@foreach($auditoria->valores_nuevos as $campo=>$nuevo)@if(($auditoria->valores_anteriores[$campo] ?? null) != $nuevo && $campo !== 'tarifa_snapshot')<small>{{ str_replace('_',' ',$campo) }}: {{ $auditoria->valores_anteriores[$campo] ?? '—' }} → {{ $nuevo ?? '—' }}</small>@endif @endforeach</li>@endforeach
            @foreach($estadia->reubicaciones->sortBy('created_at') as $movimiento)<li><time>{{ $movimiento->created_at->format('d/m/Y H:i:s') }}</time><strong>Movimiento · {{ $movimiento->origen?->codigo }} → {{ $movimiento->destino?->codigo }}</strong><span>{{ $movimiento->operador?->name }} · {{ $movimiento->motivo }}</span></li>@endforeach
            @if($estadia->hora_salida)<li><time>{{ $estadia->hora_salida->format('d/m/Y H:i:s') }}</time><strong>Salida y cobro</strong><span>S/ {{ number_format($estadia->saldo_cobrado,2) }} · {{ $estadia->operadorSalida?->name ?? 'Personal' }}</span></li>@endif
        </ol>
    @elseif($vista==='ingreso')
        <div class="space-panel-heading"><span class="eyebrow">ESPACIO LIBRE</span><h2>Registrar ingreso en {{ $espacio->codigo }}</h2><p>Emite un ticket con la tarifa vigente. El cobro de salida aparecerá aquí al registrar el ingreso.</p></div>
        <form method="post" action="{{ route('admin.estadias.store') }}" class="space-form" id="entry-form">@csrf
            <input type="hidden" id="espacio_id" name="espacio_id" value="{{ $espacio->id }}" data-fixed-space>
            <input type="hidden" name="desde_mapa" value="1">
            <div class="form-group"><label for="entry-dni">DNI (opcional)</label><input id="entry-dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off" value="{{ old('dni',request('dni')) }}" data-subscriber-url="{{ route('admin.abonados.buscar-dni') }}" data-entry-url="{{ route('admin.estadias.create') }}" placeholder="8 dígitos" autofocus></div>
            <input type="hidden" name="abono_id" id="abono_id" value="{{ old('abono_id') }}">
            <p id="entry-subscriber-message" class="entry-subscriber-message" role="status" aria-live="polite" hidden></p>
            <a id="entry-subscriber-other-space" class="btn btn-secondary" hidden>Ir al espacio habitual</a>
            <div class="form-group" id="entry-subscriber-plates" hidden><label for="entry-subscriber-plate">Placas del abonado</label><select id="entry-subscriber-plate"></select></div>
            <p id="entry-subscriber-rate" class="entry-subscriber-rate" hidden></p>
            <div class="space-form-grid"><div class="form-group"><label for="placa">Placa</label><input id="placa" name="placa" required maxlength="11" placeholder="ABC-123" value="{{ old('placa') }}"></div><div class="form-group"><label for="vehiculo_tipo_id">Tipo de vehículo</label><select id="vehiculo_tipo_id" name="vehiculo_tipo_id" data-entry-type required><option value="">Selecciona</option>@foreach($espacio->vehiculoTipos->where('activo',true) as $tipo)<option value="{{ $tipo->id }}" @selected(old('vehiculo_tipo_id')==$tipo->id)>{{ $tipo->nombre }}</option>@endforeach</select></div></div>
        @include('admin.estadias.selector-tarifa')
        @include('admin.estadias.llaves')
        <p class="text-muted" data-entry-normal-note>{{ $espacio->modo_monitoreo === 'sensor' ? 'El cobro empezará cuando el sensor confirme la ocupación.' : 'El cobro empieza al emitir el ticket.' }}</p>
        <div class="entry-submit-actions"><button class="btn btn-primary" id="entry-submit">Registrar y generar ticket</button><button type="button" class="btn btn-secondary" id="entry-release-space" hidden>Liberar espacio ocupado</button></div>
        </form>
    @elseif($vista==='reserva' && $reserva)
        <div class="space-panel-heading"><span class="eyebrow">ESPACIO RESERVADO</span><h2>Reserva {{ $reserva->codigo_corto }}</h2><p>{{ $reserva->usuario?->name }} · {{ $reserva->placa ?? 'Placa pendiente' }}</p></div><dl class="ticket-details"><div><dt>Llegada</dt><dd>{{ $reserva->fecha_reserva->format('d/m/Y') }} {{ substr($reserva->hora_inicio,0,5) }}</dd></div><div><dt>Estado</dt><dd>{{ str_replace('_',' ',$reserva->estado) }}</dd></div></dl>
        @if(($reserva->pago_al_llegar && $reserva->estado==='pendiente_pago') || ($reserva->estado==='confirmada' && $reserva->pagos->contains('estado','aprobado')))<a class="btn btn-primary" href="{{ route('admin.estadias.create',['reserva'=>$reserva->id]) }}">Registrar llegada</a>@else<a class="btn btn-secondary" href="{{ route('admin.pagos.index') }}">Revisar pago</a>@endif
    @else<div class="space-panel-heading"><h2>Espacio no disponible</h2><p>Estado: {{ str_replace('_',' ',$espacio->estado_actual) }}. Consulta el control de espacios antes de registrar un ingreso.</p><a class="btn btn-secondary" href="{{ route('admin.monitoreo.index') }}">Ver control de espacios</a></div>@endif
</main></div>
<div class="ticket-print-toast" data-ticket-toast role="status" aria-live="polite" hidden></div>
@endsection
@push('scripts')
@if($vista==='cobrar' && $liquidacion) @vite('resources/js/cobro.js') @endif
@if($vista==='ingreso') @vite(['resources/js/tarifas-ingreso.js','resources/js/ingreso-abonado.js']) @endif
<script>document.querySelectorAll('[data-confirm-form]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirmForm))event.preventDefault()}));</script>
<script>
(()=>{const toast=document.querySelector('[data-ticket-toast]');if(!toast)return;let timer;const show=(message,error=false)=>{clearTimeout(timer);toast.textContent=message;toast.setAttribute('role',error?'alert':'status');toast.classList.toggle('is-error',error);toast.hidden=false;timer=setTimeout(()=>toast.hidden=true,error?5000:1500)};document.querySelectorAll('[data-ticket-print]').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');if(button.disabled)return;button.disabled=true;try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.message||'No se pudo imprimir el ticket.');show('Impresión realizada')}catch(error){show(error.message||'No se pudo conectar con la impresora.',true)}finally{button.disabled=false}}));})();
</script>
@if($vista==='editar' && $estadia)
<script>
const editForm=document.getElementById('edit-stay-form');
if(editForm){
 const plate=document.getElementById('edit-placa'), type=document.getElementById('edit-type'), rate=document.getElementById('edit-rate');
 const filterRates=()=>{for(const option of rate.options){if(option.value)option.hidden=option.dataset.type!==type.value;}if(rate.selectedOptions[0]?.hidden)rate.value='';};
 type.addEventListener('change',()=>{rate.value='';filterRates();});filterRates();
 editForm.addEventListener('submit',event=>{
  const changes=[];
  if(plate.value.toUpperCase().replace(/[\s-]+/g,'')!==plate.dataset.original)changes.push(`Placa: ${plate.dataset.original} → ${plate.value}`);
  if(type.value!==type.dataset.original)changes.push(`Tipo: ${type.dataset.originalName} → ${type.selectedOptions[0].textContent}`);
  if(rate.value)changes.push(`Tarifa: ${rate.dataset.originalName} → ${rate.selectedOptions[0].textContent}`);
  if(!changes.length){event.preventDefault();alert('No hay cambios para guardar.');return;}
  if(type.value!==type.dataset.original&&!rate.value){event.preventDefault();alert('Selecciona una tarifa para el nuevo tipo de vehículo.');return;}
  if(!confirm(`Confirma los cambios del ticket:\n${changes.join('\n')}\nMotivo: ${document.getElementById('edit-motivo').value.trim()}`))event.preventDefault();
 });
}
</script>
@endif
@endpush
