<ol class="booking-steps" aria-label="Pasos de tu reserva">
@foreach([1=>'Datos',2=>'Revisar reserva',3=>'Pagar'] as $number=>$label)
<li class="{{ $number===$step?'current':'' }}" @if($number===$step) aria-current="step" @endif><span>{{ $number }}</span>{{ $label }}</li>
@endforeach
</ol>
