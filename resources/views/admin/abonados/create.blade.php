@extends('layouts.admin')
@section('page-title',$abonado ? 'Renovar abono' : 'Agregar abonado')
@section('page-subtitle','Registra el periodo y revisa el cobro antes de asignar el espacio.')
@push('styles') @vite('resources/css/pages/abonados.css') @endpush
@section('content')
@include('admin.estadias.messages')
@if($tarifas->isEmpty())<div class="alert alert-info">No hay tarifas mensuales activas. {{ auth()->user()->esSuperAdmin() ? 'Crea una tarifa para poder registrar el abono.' : 'Solicita al encargado general que configure una tarifa mensual.' }} @if(auth()->user()->esSuperAdmin())<a href="{{ route('admin.tarifas.create') }}">Configurar tarifa</a>@endif</div>@endif
<section class="card subscriber-form-card"><form method="post" action="{{ route('admin.abonados.preparar') }}" id="subscriber-form">@csrf
@if($abonado)<input type="hidden" name="abonado_id" value="{{ $abonado->id }}">@endif
<div id="subscriber-fields">
<div class="subscriber-form-grid">
<div class="form-group"><label for="subscriber-name">Nombre del cliente</label><input id="subscriber-name" name="nombre" required maxlength="150" value="{{ old('nombre',$abonado?->nombre) }}" @readonly($abonado)></div>
<div class="form-group"><label for="subscriber-phone">Número de teléfono</label><input id="subscriber-phone" name="telefono" required inputmode="numeric" pattern="[0-9]{9,15}" maxlength="15" value="{{ old('telefono',$abonado?->telefono) }}" @readonly($abonado && $abonado->telefono)></div>
<div class="form-group"><label for="subscriber-dni">DNI</label><input id="subscriber-dni" name="dni" required inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" value="{{ old('dni',$abonado?->dni) }}" @readonly($abonado && $abonado->dni)></div>
</div>
<div class="subscriber-field-heading"><strong>Placas autorizadas</strong><span>De 1 a 5 vehículos. Solo uno podrá ocupar el espacio a la vez.</span></div>
<div id="subscriber-plates" class="subscriber-plates">@foreach(old('placas',$abonado?->placas ?? ['']) as $placa)<div class="form-group subscriber-plate-row"><label>Placa {{ $loop->iteration }}</label><input name="placas[]" maxlength="11" pattern="[A-Za-z0-9-]{5,11}" required value="{{ $placa }}" @readonly($abonado)>@if(!$abonado && !$loop->first)<button type="button" class="subscriber-remove-plate" data-remove-plate>Quitar</button>@endif</div>@endforeach</div>
@unless($abonado)<button type="button" class="btn btn-secondary" id="add-subscriber-plate">+ Agregar placa</button>@endunless
<div class="subscriber-form-grid subscriber-contract">
<div class="form-group"><label for="subscriber-space">Espacio fijo</label><input id="subscriber-space" readonly placeholder="Selecciona en el mapa" value="{{ old('espacio_id',$ultimo?->espacio_id) ? ($espacios->firstWhere('id',old('espacio_id',$ultimo?->espacio_id))?->codigo ?? '') : '' }}"><input id="subscriber-space-id" type="hidden" name="espacio_id" required value="{{ old('espacio_id',$ultimo?->espacio_id) }}"><button type="button" class="btn btn-secondary" id="open-subscriber-map">Seleccionar en mapa</button></div>
<div class="form-group"><label for="subscriber-rate">Tarifa mensual</label><select id="subscriber-rate" name="tarifa_id" required><option value="">Seleccionar tarifa</option>@foreach($tarifas as $tarifa)<option value="{{ $tarifa->id }}" data-type="{{ $tarifa->vehiculo_tipo_id }}" data-price="{{ $tarifa->monto_base }}" @selected(old('tarifa_id',$ultimo?->tarifa_id)==$tarifa->id)>{{ $tarifa->vehiculoTipo?->nombre }} · S/ {{ number_format($tarifa->monto_base,2) }} / mes</option>@endforeach</select></div>
<div class="form-group"><label for="subscriber-months">Cantidad de meses</label><select id="subscriber-months" name="meses" required>@foreach(range(1,4) as $mes)<option value="{{ $mes }}" @selected(old('meses',1)==$mes)>{{ $mes }} {{ $mes===1?'mes':'meses' }}</option>@endforeach</select></div>
<div class="form-group"><label for="subscriber-start">Inicio del periodo</label><input id="subscriber-start" name="inicio" type="date" required value="{{ old('inicio',$ultimo?->fin?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"></div>
<div class="form-group"><label for="subscriber-end">Fecha de vencimiento</label><input id="subscriber-end" readonly></div>
<div class="form-group"><label for="subscriber-total">Total a pagar</label><input id="subscriber-total" readonly></div>
</div>
<button class="btn btn-primary subscriber-submit">Registrar y pagar</button>
</div>
<div id="subscriber-map-panel" hidden><div class="subscriber-map-heading"><div><span class="eyebrow">ESPACIO HABITUAL</span><h2>Selecciona un espacio</h2></div><button type="button" class="btn btn-secondary" id="close-subscriber-map">Volver al formulario</button></div><p class="text-muted">Puede estar ocupado temporalmente; se liberará antes de recibir al abonado.</p><div class="subscriber-map">@foreach($espacios as $e)@php($n=\App\Services\PlanoEspacios::numero($e->codigo))@if($n>=1 && $n<=41)@php([$x,$y,$w,$h]=\App\Services\PlanoEspacios::posicion($n))@php($ocupante=$e->estadias()->activas()->first())@php($available=$e->activo && !$e->tiene_abono_vigente && ($e->estado_actual==='libre' || ($ocupante && !$ocupante->abono_id)))<button type="button" class="subscriber-map-space {{ $available?'available':'unavailable' }}" style="--x:{{ $x }}%;--y:{{ $y }}%;--w:{{ $w }}%;--h:{{ $h }}%" data-space-id="{{ $e->id }}" data-space-code="{{ $e->codigo }}" data-types="{{ $e->vehiculoTipos->pluck('id')->join(',') }}" @disabled(!$available)><strong>{{ $e->codigo }}</strong><small>{{ $available ? ($ocupante ? 'Ocupado temporalmente' : 'Libre') : 'No disponible' }}</small></button>@endif @endforeach</div></div>
</form></section>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const $=id=>document.getElementById(id), form=$('subscriber-form'), panel=$('subscriber-map-panel'), fields=$('subscriber-fields');
 const rate=$('subscriber-rate'), months=$('subscriber-months'), start=$('subscriber-start');
 const refresh=()=>{const [y,m,d]=(start.value||'').split('-').map(Number), count=Number(months.value);if(y&&m&&d){const last=new Date(Date.UTC(y,m-1+count+1,0)).getUTCDate();const end=new Date(Date.UTC(y,m-1+count,Math.min(d,last)));$('subscriber-end').value=end.toISOString().slice(0,10);}else $('subscriber-end').value='';const price=Number(rate.selectedOptions[0]?.dataset.price||0);$('subscriber-total').value=price?`S/ ${(price*count).toFixed(2)}`:'';const type=rate.selectedOptions[0]?.dataset.type;document.querySelectorAll('[data-space-id]').forEach(button=>{if(!button.classList.contains('available'))return;button.disabled=!!type&&!button.dataset.types.split(',').includes(type);});};
 [rate,months,start].forEach(el=>el.addEventListener('change',refresh));refresh();
 $('open-subscriber-map').onclick=()=>{fields.hidden=true;panel.hidden=false;};$('close-subscriber-map').onclick=()=>{panel.hidden=true;fields.hidden=false;};
 document.querySelectorAll('[data-space-id]').forEach(button=>button.addEventListener('click',()=>{$('subscriber-space-id').value=button.dataset.spaceId;$('subscriber-space').value=button.dataset.spaceCode;panel.hidden=true;fields.hidden=false;}));
 const holder=$('subscriber-plates'), renumber=()=>[...holder.children].forEach((row,index)=>row.querySelector('label').textContent=`Placa ${index+1}`);
 $('add-subscriber-plate')?.addEventListener('click',()=>{const count=holder.children.length;if(count>=5)return;const row=document.createElement('div');row.className='form-group subscriber-plate-row';const label=document.createElement('label');label.textContent=`Placa ${count+1}`;const input=document.createElement('input');input.name='placas[]';input.required=true;input.maxLength=11;input.pattern='[A-Za-z0-9-]{5,11}';const remove=document.createElement('button');remove.type='button';remove.className='subscriber-remove-plate';remove.dataset.removePlate='';remove.textContent='Quitar';row.append(label,input,remove);holder.append(row);input.focus();});
 holder.addEventListener('click',event=>{if(event.target.matches('[data-remove-plate]')){event.target.closest('.subscriber-plate-row').remove();renumber();}});
 [$('subscriber-phone'),$('subscriber-dni')].forEach(input=>input.addEventListener('input',()=>input.value=input.value.replace(/\D/g,'')));
 form.addEventListener('submit',event=>{if(!$('subscriber-space-id').value){event.preventDefault();$('open-subscriber-map').click();}});
});
</script>
@endsection
